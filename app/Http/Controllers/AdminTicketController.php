<?php

namespace App\Http\Controllers;

use App\Mail\TicketAssignedMail;
use App\Mail\TicketStatusUpdatedMail;
use App\Models\ClosureReason;
use App\Models\ResolutionCategory;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketStatus;
use App\Models\TicketStatusLog;
use App\Models\User;
use App\Support\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class AdminTicketController extends Controller
{
    public function __construct(private readonly AuditService $auditService)
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
            ->when($user->role === 'ict_support_staff', fn ($query) => $query->where('assigned_to', $user->id))
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
            'statuses' => TicketStatus::query()->where('active', true)->orderBy('sort_order')->get(),
            'staff' => User::query()->where('active', true)->orderBy('name')->get(),
            'resolutionCategories' => ResolutionCategory::query()->where('active', true)->orderBy('sort_order')->get(),
            'closureReasons' => ClosureReason::query()->where('active', true)->orderBy('sort_order')->get(),
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
        $oldStatusId = $ticket->status_id;
        $oldAssignedTo = $ticket->assigned_to;
        $ticket->fill($validated);

        if (($status = TicketStatus::query()->find($validated['status_id'])) && $status->code === 'resolved') {
            $ticket->resolved_at ??= now();
        }

        if (($status ?? null) && $status->code === 'closed') {
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

        if ($oldStatusId !== $ticket->status_id) {
            TicketStatusLog::query()->create([
                'ticket_id' => $ticket->id,
                'old_status_id' => $oldStatusId,
                'new_status_id' => $ticket->status_id,
                'changed_by' => $user->id,
            ]);
        }

        $this->auditService->log('ticket.updated', $ticket, ['status_id' => $oldStatusId, 'assigned_to' => $oldAssignedTo], $ticket->fresh()->toArray(), $user->id, $request);

        if ($ticket->email && $oldAssignedTo !== $ticket->assigned_to && $ticket->assignedStaff) {
            rescue(fn () => Mail::to($ticket->email)->send(new TicketAssignedMail($ticket->fresh(['assignedStaff', 'status']))), report: false);
        }

        if ($ticket->email && $oldStatusId !== $ticket->status_id) {
            rescue(fn () => Mail::to($ticket->email)->send(new TicketStatusUpdatedMail($ticket->fresh(['status', 'assignedStaff']))), report: false);
        }

        return redirect()->route('admin.tickets.show', $ticket)->with('status', 'Ticket updated successfully.');
    }

    private function authorizeTicket(Ticket $ticket): void
    {
        $user = Auth::user();

        abort_unless(
            $user->hasAnyRole(['ict_admin', 'ict_supervisor']) || $ticket->assigned_to === $user->id,
            403
        );
    }
}
