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
use App\Support\TicketRecipientResolver;
use Illuminate\Support\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminTicketController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly TicketRecipientResolver $ticketRecipientResolver,
    )
    {
    }

    public function index(Request $request): View
    {
        $user = Auth::user();

        $tickets = Ticket::query()
            ->with(['system', 'status', 'priorityLevel', 'assignedStaff'])
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

        return view('admin.tickets.show', [
            'ticket' => $ticket->load([
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
                'comments.author',
                'statusLogs.oldStatus',
                'statusLogs.newStatus',
            ]),
            'statuses' => $this->availableStatusesFor($user),
            'staff' => $this->assignableUsersFor($user, $ticket),
            'resolutionCategories' => ResolutionCategory::query()->where('active', true)->orderBy('sort_order')->get(),
            'closureReasons' => ClosureReason::query()->where('active', true)->orderBy('sort_order')->get(),
            'isWorkflowManager' => $this->isWorkflowManager($user),
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

    public function update(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorizeTicket($ticket);

        $validated = $request->validate([
            'status_id' => ['required', 'exists:ticket_statuses,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'expected_resolution_date' => ['nullable', 'date'],
            'resolution_category_id' => ['nullable', 'exists:resolution_categories,id'],
            'closure_reason_id' => ['nullable', 'exists:closure_reasons,id'],
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
            $ticket->work_done = null;
            $ticket->recommendations = null;
            $ticket->challenges_faced = null;
            $ticket->resolution_summary = null;
        }

        if ($oldAssignedTo !== $ticket->assigned_to) {
            $ticket->assigned_at = now();
            $ticket->last_reminder_sent_at = null;
        }

        if ($oldAssignedTo !== $ticket->assigned_to && in_array($status->code, ['new', 'reopened'], true)) {
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

        if ($oldStatusId !== $ticket->status_id) {
            TicketStatusLog::query()->create([
                'ticket_id' => $ticket->id,
                'old_status_id' => $oldStatusId,
                'new_status_id' => $ticket->status_id,
                'changed_by' => $user->id,
            ]);
        }

        $this->auditService->log('ticket.updated', $ticket, ['status_id' => $oldStatusId, 'assigned_to' => $oldAssignedTo], $ticket->fresh()->toArray(), $user->id, $request);

        $freshTicket = $ticket->fresh(['system', 'status', 'assignedStaff', 'priorityLevel']);

        if ($oldAssignedTo !== $ticket->assigned_to && $ticket->assignedStaff?->email) {
            if ($status->code === 'escalated') {
                $cc = $this->ticketRecipientResolver->escalationCcEmails($ticket->assignedStaff->email);

                rescue(function () use ($freshTicket, $user, $cc) {
                    $mailer = Mail::to($freshTicket->assignedStaff->email);

                    if ($cc !== []) {
                        $mailer->cc($cc);
                    }

                    $mailer->send(new TicketEscalatedMail($freshTicket, $user));
                }, report: false);
            } else {
                rescue(fn () => Mail::to($freshTicket->assignedStaff->email)->send(new TicketWorkAssignmentMail($freshTicket)), report: false);
            }
        }

        if ($ticket->email && $oldAssignedTo !== $ticket->assigned_to && $ticket->assignedStaff) {
            rescue(fn () => Mail::to($ticket->email)->send(new TicketAssignedMail($freshTicket)), report: false);
        }

        if ($ticket->email && $oldStatusId !== $ticket->status_id) {
            rescue(fn () => Mail::to($ticket->email)->send(new TicketStatusUpdatedMail($freshTicket)), report: false);
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

    private function availableStatusesFor(User $user)
    {
        $query = TicketStatus::query()->where('active', true)->orderBy('sort_order');

        if ($this->isWorkflowManager($user)) {
            return $query->get();
        }

        return $query->whereIn('code', ['assigned', 'in_progress', 'pending_user', 'escalated', 'resolved', 'closed'])->get();
    }

    private function assignableUsersFor(User $user, Ticket $ticket)
    {
        $roles = $this->isWorkflowManager($user)
            ? [User::ROLE_ICT_SUPPORT_STAFF, User::ROLE_DEVELOPER, User::ROLE_ICT_MANAGER, User::ROLE_ICT_SUPERVISOR]
            : [User::ROLE_DEVELOPER, User::ROLE_ICT_MANAGER, User::ROLE_ICT_SUPERVISOR];

        return User::query()
            ->where('active', true)
            ->where(function ($query) use ($roles, $ticket) {
                $query->whereIn('role', $roles);

                if ($ticket->assigned_to) {
                    $query->orWhere('id', $ticket->assigned_to);
                }
            })
            ->orderBy('name')
            ->get();
    }

    private function validateWorkflowUpdate(Ticket $ticket, User $user, TicketStatus $status, ?User $assignee): void
    {
        if ($assignee && ! $assignee->hasAnyRole([
            User::ROLE_ICT_SUPPORT_STAFF,
            User::ROLE_DEVELOPER,
            User::ROLE_ICT_MANAGER,
            User::ROLE_ICT_SUPERVISOR,
        ])) {
            throw ValidationException::withMessages([
                'assigned_to' => 'Tickets can only be assigned to support, developer, manager, or supervisor accounts.',
            ]);
        }

        if ($this->isWorkflowManager($user)) {
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

            if (! $assignee->hasAnyRole([User::ROLE_DEVELOPER, User::ROLE_ICT_MANAGER, User::ROLE_ICT_SUPERVISOR])) {
                throw ValidationException::withMessages([
                    'assigned_to' => 'Assigned staff can only escalate tickets to a developer, ICT manager, or software development supervisor.',
                ]);
            }
        }

        if ($status->code === 'escalated' && (! $assignee || $assignee->id === $ticket->assigned_to)) {
            throw ValidationException::withMessages([
                'assigned_to' => 'Select a developer, ICT manager, or software development supervisor to receive the escalation.',
            ]);
        }

        $payload = request();

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
