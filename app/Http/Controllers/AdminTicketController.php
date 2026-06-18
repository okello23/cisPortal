<?php

namespace App\Http\Controllers;

use App\Mail\TicketAssignedMail;
use App\Mail\TicketEscalatedMail;
use App\Mail\TicketStatusUpdatedMail;
use App\Mail\TicketWorkAssignmentMail;
use App\Models\ClosureReason;
use App\Models\ResolutionCategory;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketStatus;
use App\Models\TicketStatusLog;
use App\Models\User;
use App\Support\AuditService;
use App\Support\TicketRecipientResolver;
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
                'region',
                'facility',
                'department',
                'issueType',
                'priorityLevel',
                'status',
                'assignedStaff',
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

    public function update(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorizeTicket($ticket);

        $validated = $request->validate([
            'status_id' => ['required', 'exists:ticket_statuses,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'expected_resolution_date' => ['nullable', 'date'],
            'resolution_category_id' => ['nullable', 'exists:resolution_categories,id'],
            'closure_reason_id' => ['nullable', 'exists:closure_reasons,id'],
            'resolution_summary' => ['nullable', 'string'],
            'comment' => ['nullable', 'string'],
            'comment_type' => ['nullable', 'in:public,internal'],
            'training_recommended' => ['nullable', 'boolean'],
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

        $ticket->training_recommended = $request->boolean('training_recommended');
        $ticket->save();

        if ($request->filled('comment')) {
            TicketComment::query()->create([
                'ticket_id' => $ticket->id,
                'comment' => $validated['comment'],
                'comment_type' => $validated['comment_type'] ?? 'internal',
                'created_by' => $user->id,
            ]);
        }

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
    }
}
