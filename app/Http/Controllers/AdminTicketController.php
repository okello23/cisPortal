<?php

namespace App\Http\Controllers;

use App\Mail\TicketAssignedMail;
use App\Mail\TicketEscalatedMail;
use App\Mail\TicketStatusUpdatedMail;
use App\Mail\TicketWorkAssignmentMail;
use App\Models\ClosureReason;
use App\Models\ResolutionCategory;
use App\Models\AuditLog;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketFeedback;
use App\Models\TicketStatus;
use App\Models\TicketStatusLog;
use App\Models\User;
use App\Support\AuditService;
use App\Support\IncidentResolutionReportService;
use App\Support\TicketRecipientResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\CarbonInterval;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminTicketController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly TicketRecipientResolver $ticketRecipientResolver,
        private readonly IncidentResolutionReportService $incidentResolutionReportService,
    )
    {
    }

    public function index(Request $request): View
    {
        $user = Auth::user();

        $tickets = Ticket::query()
            ->with(['system', 'status', 'priorityLevel', 'assignedStaff'])
            ->whereNotIn('submission_review_status', ['QUARANTINED', 'REJECTED_SPAM'])
            ->when($request->filled('status'), fn ($query) => $query->where('status_id', $request->integer('status')))
            ->when($request->filled('system'), fn ($query) => $query->where('system_id', $request->integer('system')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where(function ($nested) use ($request) {
                    $nested->where('ticket_number', 'like', '%'.$request->string('search').'%')
                        ->orWhere('full_name', 'like', '%'.$request->string('search').'%')
                        ->orWhere('description', 'like', '%'.$request->string('search').'%');
                });
            })
            ->when(! $this->isWorkflowManager($user), fn ($query) => $query->where('assigned_to', $user->id))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.tickets.index', [
            'tickets' => $tickets,
            'statuses' => TicketStatus::query()->where('active', true)->orderBy('sort_order')->get(),
            'staff' => User::query()->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function show(Ticket $ticket): View
    {
        $this->authorizeTicket($ticket);
        $user = Auth::user();
        $assignedStatusId = TicketStatus::query()->where('code', 'assigned')->value('id');

        $ticket->load([
            'system',
            'module',
            'designation',
            'region',
            'facility',
            'department',
            'issueType',
            'priorityLevel',
            'status',
            'assignedStaff',
            'feedback',
            'resolutionCategory',
            'closureReason',
            'attachments',
            'comments.author',
            'statusLogs' => fn ($query) => $query
                ->with(['oldStatus', 'newStatus', 'changedBy'])
                ->orderBy('created_at'),
        ]);

        return view('admin.tickets.show', [
            'ticket' => $ticket,
            'statusHistory' => $this->buildStatusHistory($ticket),
            'statuses' => $this->availableStatusesFor($user, $ticket),
            'staff' => $this->assignableUsersFor($user, $ticket),
            'resolutionCategories' => ResolutionCategory::query()->where('active', true)->orderBy('sort_order')->get(),
            'closureReasons' => ClosureReason::query()->where('active', true)->orderBy('sort_order')->get(),
            'isWorkflowManager' => $this->isWorkflowManager($user),
            'assignedStatusId' => $assignedStatusId,
        ]);
    }

    public function auditTrail(Ticket $ticket): View
    {
        $this->authorizeTicket($ticket);

        $ticket->load([
            'system',
            'status',
            'assignedStaff',
            'feedback',
            'comments.author',
            'statusLogs.oldStatus',
            'statusLogs.newStatus',
            'statusLogs.changedBy',
        ]);

        return view('admin.tickets.audit-trail', [
            'ticket' => $ticket,
            'timeline' => $this->buildTimeline($ticket),
        ]);
    }

    public function downloadIncidentResolutionReport(Ticket $ticket): Response
    {
        $this->authorizeTicket($ticket);

        $ticket->load('feedback');

        abort_if($ticket->resolved_at === null && $ticket->closed_at === null, 404);

        if ($ticket->feedback) {
            $path = $this->incidentResolutionReportService->generateForFeedback($ticket, $ticket->feedback);

            $ticket->feedback->forceFill([
                'incident_report_path' => $path,
                'incident_report_generated_at' => now(),
            ])->save();
        } else {
            $path = $this->incidentResolutionReportService->generateForTicket($ticket);
        }

        return response(
            Storage::disk('local')->get($path),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$ticket->ticket_number.'-incident-resolution-report.pdf"',
            ]
        );
    }

    public function update(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorizeTicket($ticket);

        $validated = $request->validate([
            'status_id' => ['required', 'exists:ticket_statuses,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'expected_resolution_date' => ['nullable', 'date'],
            'comment' => ['nullable', 'string'],
            'resolution_category_id' => ['nullable', 'exists:resolution_categories,id'],
            'closure_reason_id' => ['nullable', 'exists:closure_reasons,id'],
            'root_cause_analysis' => ['nullable', 'string'],
            'verification_testing' => ['nullable', 'string'],
            'data_loss_risk' => ['nullable', 'string', Rule::in(['none', 'partial', 'full'])],
            'services_disrupted' => ['nullable', 'string'],
            'work_done' => ['nullable', 'string'],
            'recommendations' => ['nullable', 'string'],
            'challenges_faced' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $status = TicketStatus::query()->findOrFail($validated['status_id']);
        $assignee = ! empty($validated['assigned_to'])
            ? User::query()->where('active', true)->find($validated['assigned_to'])
            : null;

        $this->validateWorkflowUpdate($ticket, $user, $status, $assignee);

        $oldStatusId = $ticket->status_id;
        $oldAssignedTo = $ticket->assigned_to;
        $ticket->fill($validated);

        if ($status->code !== 'resolved') {
            $ticket->resolution_category_id = null;
        }

        if ($status->code !== 'closed') {
            $ticket->closure_reason_id = null;
        }

        if (in_array($status->code, ['resolved', 'closed'], true)) {
            $ticket->resolution_summary = $validated['work_done'] ?? null;
        } else {
            $ticket->root_cause_analysis = null;
            $ticket->verification_testing = null;
            $ticket->data_loss_risk = null;
            $ticket->services_disrupted = null;
            $ticket->work_done = null;
            $ticket->recommendations = null;
            $ticket->challenges_faced = null;
            $ticket->resolution_summary = null;
        }

        if ($oldAssignedTo !== $ticket->assigned_to) {
            $ticket->assigned_at = now();
            $ticket->last_reminder_sent_at = null;
        }

        $isDashboardAssignment = $request->boolean('assignment_modal')
            && $assignee !== null
            && $oldAssignedTo !== $assignee->id;

        if ($isDashboardAssignment) {
            $status = TicketStatus::query()->where('code', 'assigned')->firstOrFail();
            $ticket->status_id = $status->id;
        } elseif ($oldAssignedTo !== $ticket->assigned_to && in_array($status->code, ['new', 'reopened'], true)) {
            $status = TicketStatus::query()->where('code', 'assigned')->firstOrFail();
            $ticket->status_id = $status->id;
        }

        $ticket->last_worked_at = now();

        if ($status->code === 'resolved') {
            $ticket->resolved_at ??= now();
        }

        if ($status->code === 'closed') {
            $ticket->closed_at ??= now();
        }

        $ticket->save();

        if ($status->code === 'escalated' && $assignee) {
            TicketComment::query()->create([
                'ticket_id' => $ticket->id,
                'comment' => 'Ticket escalated to '.$assignee->name.' by '.$user->name.'.',
                'comment_type' => 'internal',
                'created_by' => $user->id,
            ]);
        }

        if ($oldAssignedTo !== $ticket->assigned_to && $request->filled('comment')) {
            TicketComment::query()->create([
                'ticket_id' => $ticket->id,
                'comment' => $request->string('comment')->trim()->toString(),
                'comment_type' => 'internal',
                'created_by' => $user->id,
            ]);
        }

        if ($oldStatusId !== $ticket->status_id) {
            TicketStatusLog::query()->create([
                'ticket_id' => $ticket->id,
                'old_status_id' => $oldStatusId,
                'new_status_id' => $ticket->status_id,
                'changed_by' => $user->id,
            ]);
        }

        $persistedTicket = $ticket->fresh();

        $this->auditService->log('ticket.updated', $ticket, ['status_id' => $oldStatusId, 'assigned_to' => $oldAssignedTo], $persistedTicket?->toArray(), $user->id, $request);

        $mailTicket = Ticket::query()
            ->with(['system', 'status', 'assignedStaff', 'priorityLevel'])
            ->findOrFail($ticket->getKey());

        if ($oldAssignedTo !== $ticket->assigned_to && $ticket->assignedStaff?->email) {
            if ($status->code === 'escalated') {
                $cc = $this->ticketRecipientResolver->escalationCcEmails($ticket->assignedStaff->email);

                dispatch(function () use ($mailTicket, $user, $cc) {
                    rescue(function () use ($mailTicket, $user, $cc) {
                        $mailer = Mail::to($mailTicket->assignedStaff->email);

                        if ($cc !== []) {
                            $mailer->cc($cc);
                        }

                        $mailer->send(new TicketEscalatedMail($mailTicket, $user));
                    }, report: false);
                })->afterResponse();
            } else {
                dispatch(function () use ($mailTicket) {
                    rescue(fn () => Mail::to($mailTicket->assignedStaff->email)->send(new TicketWorkAssignmentMail($mailTicket)), report: false);
                })->afterResponse();
            }
        }

        if ($ticket->email && $oldAssignedTo !== $ticket->assigned_to && $ticket->assignedStaff) {
            dispatch(function () use ($ticket, $mailTicket) {
                rescue(fn () => Mail::to($ticket->email)->send(new TicketAssignedMail($mailTicket)), report: false);
            })->afterResponse();
        }

        if ($ticket->email && $oldStatusId !== $ticket->status_id) {
            dispatch(function () use ($ticket, $mailTicket) {
                rescue(fn () => Mail::to($ticket->email)->send(new TicketStatusUpdatedMail($mailTicket)), report: false);
            })->afterResponse();
        }

        if ($request->boolean('return_to_dashboard')) {
            return redirect()
                ->route('dashboard')
                ->with('status', 'Ticket assigned successfully.');
        }

        return redirect()->route('admin.tickets.show', $ticket)->with('status', 'Ticket updated successfully.');
    }

    private function authorizeTicket(Ticket $ticket): void
    {
        $user = Auth::user();

        abort_unless(
            $this->isWorkflowManager($user) || $ticket->assigned_to === $user->id,
            403
        );
    }

    private function buildTimeline(Ticket $ticket): Collection
    {
        $feedbackAuditIds = $ticket->feedback
            ? [$ticket->feedback->getKey()]
            : [];

        $auditLogs = AuditLog::query()
            ->with('user:id,name')
            ->where(function ($query) use ($ticket, $feedbackAuditIds) {
                $query->where(function ($ticketQuery) use ($ticket) {
                    $ticketQuery->where('auditable_type', Ticket::class)
                        ->where('auditable_id', $ticket->getKey());
                });

                if ($feedbackAuditIds !== []) {
                    $query->orWhere(function ($feedbackQuery) use ($feedbackAuditIds) {
                        $feedbackQuery->where('auditable_type', TicketFeedback::class)
                            ->whereIn('auditable_id', $feedbackAuditIds);
                    });
                }
            })
            ->orderBy('created_at')
            ->get();

        $events = collect();

        foreach ($auditLogs as $log) {
            $label = match ($log->action) {
                'ticket.created' => 'Ticket created',
                'ticket.feedback_submitted' => 'Customer feedback submitted',
                default => null,
            };

            if ($label !== null) {
                $events->push([
                    'timestamp' => $log->created_at,
                    'title' => $label,
                    'actor' => $log->user?->name ?? 'System',
                    'tone' => $log->action === 'ticket.feedback_submitted' ? 'success' : 'primary',
                    'details' => $this->detailsForAuditLog($log),
                ]);
            }

            if ($log->action === 'ticket.updated') {
                foreach ($this->eventsForTicketUpdate($log, $ticket) as $event) {
                    $events->push($event);
                }
            }
        }

        foreach ($ticket->statusLogs as $log) {
            $events->push([
                'timestamp' => $log->created_at,
                'title' => 'Status changed',
                'actor' => $log->changedBy?->name ?? 'System',
                'tone' => 'info',
                'details' => trim(($log->oldStatus?->name ?? 'New Ticket').' -> '.($log->newStatus?->name ?? 'Unknown')),
            ]);
        }

        foreach ($ticket->comments as $comment) {
            $events->push([
                'timestamp' => $comment->created_at,
                'title' => ucfirst($comment->comment_type).' comment added',
                'actor' => $comment->author?->name ?? 'System',
                'tone' => $comment->comment_type === 'public' ? 'success' : 'secondary',
                'details' => $comment->comment,
            ]);
        }

        return $events
            ->sortByDesc(fn (array $event) => $event['timestamp'])
            ->values();
    }

    private function buildStatusHistory(Ticket $ticket): Collection
    {
        $previousTimestamp = $ticket->created_at;

        return $ticket->statusLogs->map(function (TicketStatusLog $log) use (&$previousTimestamp) {
            $changedAt = $log->created_at;
            $tat = $previousTimestamp
                ? $this->formatTurnaroundTime($previousTimestamp->diff($changedAt))
                : 'N/A';

            $previousTimestamp = $changedAt;

            return [
                'old_status' => $log->oldStatus?->name ?? 'New Ticket',
                'new_status' => $log->newStatus?->name ?? 'N/A',
                'changed_at' => $changedAt,
                'changed_by' => $log->changedBy?->name ?? 'System',
                'tat' => $tat,
            ];
        });
    }

    private function formatTurnaroundTime(\DateInterval $interval): string
    {
        $parts = [];
        $units = [
            'd' => 'd',
            'h' => 'h',
            'i' => 'm',
            's' => 's',
        ];

        foreach ($units as $property => $suffix) {
            $value = $interval->{$property};

            if ($value > 0) {
                $parts[] = $value.$suffix;
            }

            if (count($parts) === 2) {
                break;
            }
        }

        if ($interval->y > 0 || $interval->m > 0) {
            return CarbonInterval::instance($interval)->cascade()->forHumans(short: true);
        }

        return $parts !== [] ? implode(' ', $parts) : '0s';
    }

    private function detailsForAuditLog(AuditLog $log): string
    {
        if ($log->action === 'ticket.feedback_submitted') {
            $newValues = $log->new_values ?? [];

            return 'Timeliness '.($newValues['timeliness_rating'] ?? '?').'/5, Completeness '.($newValues['completeness_rating'] ?? '?').'/5, Overall '.($newValues['overall_satisfaction_rating'] ?? '?').'/5';
        }

        return 'Recorded by the workflow.';
    }

    private function eventsForTicketUpdate(AuditLog $log, Ticket $ticket): array
    {
        $oldValues = $log->old_values ?? [];
        $newValues = $log->new_values ?? [];
        $events = [];
        $actor = $log->user?->name ?? 'System';

        if (($oldValues['assigned_to'] ?? null) !== ($newValues['assigned_to'] ?? null)) {
            $oldAssignee = $this->userLabel((int) ($oldValues['assigned_to'] ?? 0), $ticket);
            $newAssignee = $this->userLabel((int) ($newValues['assigned_to'] ?? 0), $ticket);

            $events[] = [
                'timestamp' => $log->created_at,
                'title' => ($oldValues['assigned_to'] ?? null) ? 'Ticket reassigned' : 'Ticket assigned',
                'actor' => $actor,
                'tone' => 'warning',
                'details' => trim(($oldAssignee ? $oldAssignee.' -> ' : '').($newAssignee ?: 'Unassigned')),
            ];
        }

        if (($oldValues['expected_resolution_date'] ?? null) !== ($newValues['expected_resolution_date'] ?? null)
            && ! empty($newValues['expected_resolution_date'])) {
            $events[] = [
                'timestamp' => $log->created_at,
                'title' => 'Expected resolution date updated',
                'actor' => $actor,
                'tone' => 'secondary',
                'details' => 'New target: '.$newValues['expected_resolution_date'],
            ];
        }

        if (($oldValues['resolution_summary'] ?? null) !== ($newValues['resolution_summary'] ?? null)
            && ! empty($newValues['resolution_summary'])) {
            $events[] = [
                'timestamp' => $log->created_at,
                'title' => 'Resolution summary updated',
                'actor' => $actor,
                'tone' => 'secondary',
                'details' => (string) $newValues['resolution_summary'],
            ];
        }

        if (($oldValues['training_recommended'] ?? null) !== ($newValues['training_recommended'] ?? null)) {
            $events[] = [
                'timestamp' => $log->created_at,
                'title' => 'Training recommendation updated',
                'actor' => $actor,
                'tone' => 'secondary',
                'details' => ! empty($newValues['training_recommended']) ? 'Training recommended' : 'Training not recommended',
            ];
        }

        return $events;
    }

    private function userLabel(int $userId, Ticket $ticket): ?string
    {
        if ($userId === 0) {
            return null;
        }

        if ($ticket->assignedStaff && $ticket->assignedStaff->getKey() === $userId) {
            return $ticket->assignedStaff->name;
        }

        return User::query()->whereKey($userId)->value('name');
    }

    private function isWorkflowManager(User $user): bool
    {
        return $user->hasAnyRole([
            User::ROLE_ICT_ADMIN,
            User::ROLE_ICT_MANAGER,
            User::ROLE_ICT_SUPERVISOR,
        ]);
    }

    private function availableStatusesFor(User $user, Ticket $ticket)
    {
        $query = TicketStatus::query()->where('active', true)->orderBy('sort_order');

        if ($ticket->status?->code === 'assigned') {
            return $query->whereIn('code', ['escalated', 'resolved'])->get();
        }

        if ($this->isWorkflowManager($user)) {
            return $query->get();
        }

        return $query->whereIn('code', ['assigned', 'in_progress', 'pending_user', 'escalated', 'resolved', 'closed'])->get();
    }

    private function assignableUsersFor(User $user, Ticket $ticket)
    {
        $roles = $this->isWorkflowManager($user)
            ? [User::ROLE_ICT_SUPPORT_STAFF, User::ROLE_DEVELOPER, User::ROLE_ICT_SUPERVISOR, User::ROLE_ICT_ADMIN, User::ROLE_ICT_MANAGER]
            : [User::ROLE_DEVELOPER, User::ROLE_ICT_SUPERVISOR, User::ROLE_ICT_ADMIN, User::ROLE_ICT_MANAGER];

        $rolePriority = [
            User::ROLE_ICT_SUPPORT_STAFF => 1,
            User::ROLE_DEVELOPER => 2,
            User::ROLE_ICT_SUPERVISOR => 3,
            User::ROLE_ICT_ADMIN => 4,
            User::ROLE_ICT_MANAGER => 5,
        ];

        return User::query()
            ->where('active', true)
            ->where(function ($query) use ($roles, $ticket) {
                $query->whereIn('role', $roles);

                if ($ticket->assigned_to) {
                    $query->orWhere('id', $ticket->assigned_to);
                }
            })
            ->get()
            ->sort(function (User $left, User $right) use ($rolePriority) {
                $leftPriority = $rolePriority[$left->role] ?? 99;
                $rightPriority = $rolePriority[$right->role] ?? 99;

                if ($leftPriority !== $rightPriority) {
                    return $leftPriority <=> $rightPriority;
                }

                return strcasecmp($left->name, $right->name);
            })
            ->values();
    }

    private function validateWorkflowUpdate(Ticket $ticket, User $user, TicketStatus $status, ?User $assignee): void
    {
        $payload = request();

        if ($assignee && ! $assignee->hasAnyRole([
            User::ROLE_ICT_SUPPORT_STAFF,
            User::ROLE_DEVELOPER,
            User::ROLE_ICT_ADMIN,
            User::ROLE_ICT_MANAGER,
            User::ROLE_ICT_SUPERVISOR,
        ])) {
            throw ValidationException::withMessages([
                'assigned_to' => 'Tickets can only be assigned to support, developer, admin, manager, or supervisor accounts.',
            ]);
        }

        if ($this->isWorkflowManager($user)) {
            if ($ticket->status?->code === 'new' && ! $assignee) {
                throw ValidationException::withMessages([
                    'assigned_to' => 'Choose the support staff member receiving this new ticket.',
                ]);
            }

            if ($assignee && ! $payload->filled('expected_resolution_date')) {
                throw ValidationException::withMessages([
                    'expected_resolution_date' => 'Expected resolution date is required when assigning a ticket.',
                ]);
            }

            if ($status->code === 'escalated' && ! $assignee) {
                throw ValidationException::withMessages([
                    'assigned_to' => 'Choose the person receiving the escalation.',
                ]);
            }

            return;
        }

        if ($ticket->assigned_to !== $user->id) {
            throw ValidationException::withMessages([
                'assigned_to' => 'You can only update tickets assigned to you.',
            ]);
        }

        if ($assignee && $assignee->id !== $ticket->assigned_to) {
            if ($status->code !== 'escalated') {
                throw ValidationException::withMessages([
                    'status_id' => 'Select the Escalated status when handing a ticket to another person.',
                ]);
            }

            if (! $assignee->hasAnyRole([User::ROLE_DEVELOPER, User::ROLE_ICT_ADMIN, User::ROLE_ICT_MANAGER, User::ROLE_ICT_SUPERVISOR])) {
                throw ValidationException::withMessages([
                    'assigned_to' => 'Assigned staff can only escalate tickets to a developer, admin, ICT manager, or software development supervisor.',
                ]);
            }
        }

        if ($status->code === 'escalated' && (! $assignee || $assignee->id === $ticket->assigned_to)) {
            throw ValidationException::withMessages([
                'assigned_to' => 'Select a developer, admin, ICT manager, or software development supervisor to receive the escalation.',
            ]);
        }

        if ($status->code === 'resolved') {
            throw_if(! $payload->filled('resolution_category_id'), ValidationException::withMessages([
                'resolution_category_id' => 'Choose a resolution category when resolving a ticket.',
            ]));
        }

        if ($status->code === 'closed') {
            throw_if(! $payload->filled('closure_reason_id'), ValidationException::withMessages([
                'closure_reason_id' => 'Choose a closure reason when closing a ticket.',
            ]));
        }

        if (in_array($status->code, ['resolved', 'closed'], true)) {
            $messages = [];

            if (! $payload->filled('root_cause_analysis')) {
                $messages['root_cause_analysis'] = 'Root cause analysis is required when resolving or closing a ticket.';
            }

            if (! $payload->filled('verification_testing')) {
                $messages['verification_testing'] = 'Verification / testing is required when resolving or closing a ticket.';
            }

            if (! $payload->filled('data_loss_risk')) {
                $messages['data_loss_risk'] = 'Data loss risk is required when resolving or closing a ticket.';
            }

            if (! $payload->filled('services_disrupted')) {
                $messages['services_disrupted'] = 'Services disrupted is required when resolving or closing a ticket.';
            }

            if (! $payload->filled('work_done')) {
                $messages['work_done'] = 'Work done is required when resolving or closing a ticket.';
            }

            if (! $payload->filled('recommendations')) {
                $messages['recommendations'] = 'Recommendations are required when resolving or closing a ticket.';
            }

            if ($messages !== []) {
                throw ValidationException::withMessages($messages);
            }
        }
    }
}
