<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PriorityLevel;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\TicketStatusLog;
use App\Support\TicketNumberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketApiController extends Controller
{
    public function __construct(private readonly TicketNumberService $ticketNumberService)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'system_id' => ['required', 'exists:support_systems,id'],
            'module_id' => ['nullable', 'exists:support_modules,id'],
            'designation_id' => ['required', 'exists:designations,id'],
            'issue_started_at' => ['required', 'date'],
            'full_name' => ['required', 'string'],
            'phone' => ['nullable', 'string', 'required_without:email'],
            'email' => ['nullable', 'email', 'required_without:phone'],
            'lab_manager_name' => ['required', 'string'],
            'lab_manager_email' => ['required', 'email'],
            'region_id' => ['required', 'exists:regions,id'],
            'district_name' => ['required', 'string'],
            'facility_id' => ['required', 'exists:facilities,id'],
            'issue_type_id' => ['required', 'exists:issue_types,id'],
            'priority_level_id' => ['required', 'exists:priority_levels,id'],
            'description' => ['required', 'string'],
        ]);

        $status = TicketStatus::query()->where('code', 'new')->firstOrFail();
        $priority = PriorityLevel::query()->findOrFail($validated['priority_level_id']);

        $ticket = Ticket::query()->create([
            ...$validated,
            'ticket_number' => $this->ticketNumberService->generate(),
            'source_url' => $request->headers->get('referer'),
            'browser_info' => (string) $request->userAgent(),
            'device_info' => 'API',
            'ip_address' => $request->ip(),
            'status_id' => $status->id,
            'expected_resolution_date' => now()->addHours($priority->sla_hours)->toDateString(),
        ]);

        TicketStatusLog::query()->create([
            'ticket_id' => $ticket->id,
            'new_status_id' => $status->id,
        ]);

        return response()->json([
            'ticket_number' => $ticket->ticket_number,
            'status' => 'created',
        ], 201);
    }
}
