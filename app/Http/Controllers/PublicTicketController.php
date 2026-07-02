<?php

namespace App\Http\Controllers;

use App\Mail\NewTicketAlertMail;
use App\Mail\TicketConfirmationMail;
use App\Models\Designation;
use App\Models\Facility;
use App\Models\IssueType;
use App\Models\PriorityLevel;
use App\Models\Region;
use App\Models\SupportSystem;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\TicketStatusLog;
use App\Support\AuditService;
use App\Support\TicketNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class PublicTicketController extends Controller
{
    public function __construct(
        private readonly TicketNumberService $ticketNumberService,
        private readonly AuditService $auditService,
    ) {
    }

    public function create(Request $request): View
    {
        return view('tickets.create', [
            'selectedSystem' => null,
            'designations' => Designation::query()->where('active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'systems' => SupportSystem::query()->where('active', true)->orderBy('sort_order')->get(),
            'regions' => Region::query()->where('active', true)->orderBy('sort_order')->get(),
            'facilities' => Facility::query()->where('active', true)->orderBy('sort_order')->get(),
            'issueTypes' => IssueType::query()->where('active', true)->orderBy('sort_order')->get(),
            'priorityLevels' => PriorityLevel::query()->where('active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'system_id' => ['required', 'exists:support_systems,id'],
            'designation_id' => ['required', 'exists:designations,id'],
            'issue_started_at' => ['required', 'date', 'before_or_equal:today'],
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'lab_manager_name' => ['required', 'string', 'max:255'],
            'lab_manager_email' => ['required', 'email', 'max:255'],
            'region_id' => ['required', 'exists:regions,id'],
            'district_name' => ['required', 'string', 'max:255'],
            'facility_id' => ['required', 'exists:facilities,id'],
            'issue_type_id' => ['required', 'exists:issue_types,id'],
            'priority_level_id' => ['required', 'exists:priority_levels,id'],
            'description' => ['required', 'string', 'min:10'],
            'attachment' => ['nullable', 'file', 'max:4096'],
        ]);

        $status = TicketStatus::query()->where('code', 'new')->firstOrFail();
        $priority = PriorityLevel::query()->findOrFail($validated['priority_level_id']);
        $attachmentPath = $request->file('attachment')?->store('tickets', 'public');

        $ticket = Ticket::query()->create([
            ...$validated,
            'ticket_number' => $this->ticketNumberService->generate(),
            'attachment_path' => $attachmentPath,
            'browser_info' => (string) $request->userAgent(),
            'device_info' => (string) $request->header('Sec-CH-UA-Platform', 'Unknown'),
            'ip_address' => $request->ip(),
            'status_id' => $status->id,
            'expected_resolution_date' => now()->addHours($priority->sla_hours)->toDateString(),
            'training_recommended' => false,
        ]);

        TicketStatusLog::query()->create([
            'ticket_id' => $ticket->id,
            'new_status_id' => $status->id,
        ]);

        $this->auditService->log('ticket.created', $ticket, null, $ticket->toArray(), null, $request);

        rescue(function () use ($ticket) {
            Mail::to(config('mail.from.address', 'ictsupport@cphl.go.ug'))
                ->send(new NewTicketAlertMail($ticket->load(['system', 'module', 'designation', 'issueType', 'priorityLevel'])));
        }, report: false);

        if ($ticket->email) {
            rescue(function () use ($ticket) {
                $mailer = Mail::to($ticket->email);

                if ($ticket->lab_manager_email && strcasecmp($ticket->lab_manager_email, $ticket->email) !== 0) {
                    $mailer->cc($ticket->lab_manager_email);
                }

                $mailer->send(new TicketConfirmationMail($ticket->load('status')));
            }, report: false);
        }

        return redirect()
            ->route('tickets.track')
            ->with('ticket_number', $ticket->ticket_number)
            ->with('status', 'Support ticket submitted successfully.');
    }
}
