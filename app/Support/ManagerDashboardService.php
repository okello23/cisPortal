<?php

namespace App\Support;

use App\Models\Facility;
use App\Models\IssueType;
use App\Models\Region;
use App\Models\SupportSystem;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketStatusLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ManagerDashboardService
{
    public function build(Request $request): array
    {
        $filters = $this->filters($request);
        $query = $this->filteredQuery($filters);

        $tickets = $query
            ->with(['status', 'priorityLevel', 'system', 'facility', 'region', 'issueType', 'assignedStaff', 'resolutionCategory', 'feedback'])
            ->get();

        $ticketIds = $tickets->pluck('id');

        $comments = TicketComment::query()
            ->whereIn('ticket_id', $ticketIds)
            ->whereNotNull('created_by')
            ->orderBy('created_at')
            ->get();

        $statusLogs = TicketStatusLog::query()
            ->with(['newStatus:id,code,name', 'changedBy:id,name,role'])
            ->whereIn('ticket_id', $ticketIds)
            ->whereNotNull('changed_by')
            ->orderBy('created_at')
            ->get();

        return [
            'filters' => $filters,
            'filter_options' => $this->filterOptions($filters),
            'summary' => $this->summary($tickets, $comments, $statusLogs),
            'tickets_by_status' => $this->countBy($tickets, fn (Ticket $ticket) => $ticket->status?->name ?? 'Unspecified'),
            'tickets_by_priority' => $this->countBy($tickets, fn (Ticket $ticket) => $ticket->priorityLevel?->name ?? 'Unspecified'),
            'tickets_by_system' => $this->countBy($tickets, fn (Ticket $ticket) => $ticket->system?->name ?? 'Unspecified', 8),
            'tickets_by_facility' => $this->countBy($tickets, fn (Ticket $ticket) => $ticket->facility?->name ?? 'Unspecified', 8),
            'tickets_by_region' => $this->countBy($tickets, fn (Ticket $ticket) => $ticket->region?->name ?? 'Unspecified'),
            'staff_workload' => $this->staffWorkload($tickets),
            'top_performing_staff' => $this->staffPerformanceRows($tickets)->sortByDesc('score')->take(5)->values(),
            'lowest_rated_staff' => $this->lowestRatedStaff($tickets),
            'customer_satisfaction_trends' => $this->customerSatisfactionTrends($tickets),
            'resolution_code_statistics' => $this->countBy($tickets->filter(fn (Ticket $ticket) => $ticket->resolutionCategory !== null), fn (Ticket $ticket) => $ticket->resolutionCategory?->name ?? 'Unspecified'),
            'monthly_ticket_trends' => $this->monthlyTicketTrends($tickets),
            'annual_ticket_trends' => $this->annualTicketTrends($tickets),
        ];
    }

    private function filters(Request $request): array
    {
        return [
            'start_date' => $request->string('manager_start_date')->toString(),
            'end_date' => $request->string('manager_end_date')->toString(),
            'region_id' => $request->integer('manager_region_id') ?: null,
            'facility_id' => $request->integer('manager_facility_id') ?: null,
            'system_id' => $request->integer('manager_system_id') ?: null,
            'support_staff_id' => $request->integer('manager_support_staff_id') ?: null,
            'issue_type_id' => $request->integer('manager_issue_type_id') ?: null,
        ];
    }

    private function filteredQuery(array $filters)
    {
        return Ticket::query()
            ->whereNotIn('submission_review_status', ['QUARANTINED', 'REJECTED_SPAM'])
            ->when($filters['start_date'] !== '' && $filters['end_date'] !== '', function ($query) use ($filters) {
                $start = Carbon::parse($filters['start_date'])->startOfDay();
                $end = Carbon::parse($filters['end_date'])->endOfDay();

                if ($start->gt($end)) {
                    [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
                }

                $query->whereBetween('created_at', [$start, $end]);
            })
            ->when($filters['region_id'], fn ($query, $value) => $query->where('region_id', $value))
            ->when($filters['facility_id'], fn ($query, $value) => $query->where('facility_id', $value))
            ->when($filters['system_id'], fn ($query, $value) => $query->where('system_id', $value))
            ->when($filters['support_staff_id'], fn ($query, $value) => $query->where('assigned_to', $value))
            ->when($filters['issue_type_id'], fn ($query, $value) => $query->where('issue_type_id', $value));
    }

    private function filterOptions(array $filters): array
    {
        return [
            'regions' => Region::query()->where('active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'facilities' => Facility::query()
                ->where('active', true)
                ->when($filters['region_id'], fn ($query, $value) => $query->where('region_id', $value))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name']),
            'systems' => SupportSystem::query()->where('active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'support_staff' => User::query()
                ->where('active', true)
                ->where('role', User::ROLE_ICT_SUPPORT_STAFF)
                ->orderBy('name')
                ->get(['id', 'name']),
            'issue_types' => IssueType::query()->where('active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
        ];
    }

    private function summary(Collection $tickets, Collection $comments, Collection $statusLogs): array
    {
        $resolvedTickets = $tickets->filter(fn (Ticket $ticket) => $ticket->resolved_at !== null)->values();
        $openTickets = $tickets->filter(fn (Ticket $ticket) => ! in_array($ticket->status?->code, ['resolved', 'closed'], true))->values();

        return [
            'total_open_tickets' => $openTickets->count(),
            'average_response_time_hours' => $this->averageResponseTimeHours($tickets, $comments, $statusLogs),
            'average_resolution_time_hours' => $this->averageResolutionTimeHours($resolvedTickets),
            'sla_compliance_rate' => $this->slaComplianceRate($resolvedTickets),
            'overdue_tickets' => $openTickets->filter(fn (Ticket $ticket) => $ticket->expected_resolution_date && $ticket->expected_resolution_date->endOfDay()->lt(now()))->count(),
            'escalated_tickets' => $tickets->filter(fn (Ticket $ticket) => $ticket->status?->code === 'escalated')->count(),
        ];
    }

    private function averageResponseTimeHours(Collection $tickets, Collection $comments, Collection $statusLogs): ?float
    {
        $hours = $tickets->map(function (Ticket $ticket) use ($comments, $statusLogs) {
            if ($ticket->assigned_at === null) {
                return null;
            }

            $firstCommentAt = $comments
                ->where('ticket_id', $ticket->id)
                ->filter(fn (TicketComment $comment) => $comment->created_at->gte($ticket->assigned_at))
                ->sortBy('created_at')
                ->first()?->created_at;

            $firstStatusAt = $statusLogs
                ->where('ticket_id', $ticket->id)
                ->filter(fn (TicketStatusLog $log) => $log->created_at->gte($ticket->assigned_at) && $log->newStatus?->code !== 'assigned')
                ->sortBy('created_at')
                ->first()?->created_at;

            $firstTouch = collect([$firstCommentAt, $firstStatusAt])->filter()->sort()->first();

            return $firstTouch ? $ticket->assigned_at->diffInSeconds($firstTouch) / 3600 : null;
        })->filter(fn ($value) => $value !== null)->values();

        return $hours->isEmpty() ? null : round($hours->avg(), 2);
    }

    private function averageResolutionTimeHours(Collection $resolvedTickets): ?float
    {
        $hours = $resolvedTickets
            ->map(fn (Ticket $ticket) => $ticket->created_at && $ticket->resolved_at ? $ticket->created_at->diffInSeconds($ticket->resolved_at) / 3600 : null)
            ->filter(fn ($value) => $value !== null)
            ->values();

        return $hours->isEmpty() ? null : round($hours->avg(), 2);
    }

    private function slaComplianceRate(Collection $resolvedTickets): ?float
    {
        $eligible = $resolvedTickets->filter(fn (Ticket $ticket) => $ticket->expected_resolution_date !== null)->values();

        if ($eligible->isEmpty()) {
            return null;
        }

        $compliant = $eligible->filter(fn (Ticket $ticket) => $ticket->resolved_at?->toDateString() <= $ticket->expected_resolution_date?->toDateString())->count();

        return round(($compliant / $eligible->count()) * 100, 2);
    }

    private function countBy(Collection $tickets, callable $resolver, ?int $limit = null): Collection
    {
        $rows = $tickets
            ->groupBy(fn (Ticket $ticket) => $resolver($ticket))
            ->map(fn (Collection $group, string $label) => ['label' => $label, 'value' => $group->count()])
            ->sortByDesc('value')
            ->values();

        return $limit ? $rows->take($limit)->values() : $rows;
    }

    private function staffWorkload(Collection $tickets): Collection
    {
        return $tickets
            ->filter(fn (Ticket $ticket) => $ticket->assignedStaff !== null)
            ->groupBy(fn (Ticket $ticket) => $ticket->assignedStaff?->name ?? 'Unassigned')
            ->map(function (Collection $group, string $name) {
                $active = $group->filter(fn (Ticket $ticket) => ! in_array($ticket->status?->code, ['resolved', 'closed'], true))->count();
                $resolved = $group->filter(fn (Ticket $ticket) => $ticket->status?->code === 'resolved')->count();
                $closed = $group->filter(fn (Ticket $ticket) => $ticket->status?->code === 'closed')->count();

                return [
                    'label' => $name,
                    'active' => $active,
                    'resolved' => $resolved,
                    'closed' => $closed,
                    'total' => $group->count(),
                ];
            })
            ->sortByDesc('active')
            ->values();
    }

    private function staffPerformanceRows(Collection $tickets): Collection
    {
        return $this->staffWorkload($tickets)->map(function (array $workload) use ($tickets) {
            $staffTickets = $tickets->filter(fn (Ticket $ticket) => $ticket->assignedStaff?->name === $workload['label'])->values();
            $resolved = $staffTickets->filter(fn (Ticket $ticket) => $ticket->resolved_at !== null)->count();
            $sla = $this->slaComplianceRate($staffTickets->filter(fn (Ticket $ticket) => $ticket->resolved_at !== null)->values()) ?? 0;
            $rating = $this->averageRatingForTickets($staffTickets) ?? 0;

            return [
                'label' => $workload['label'],
                'resolved' => $resolved,
                'active' => $workload['active'],
                'sla_compliance_rate' => $sla,
                'average_rating' => $rating,
                'score' => round(($resolved * 2) + $sla + ($rating * 20) - ($workload['active'] * 0.5), 2),
            ];
        });
    }

    private function lowestRatedStaff(Collection $tickets): Collection
    {
        return $tickets
            ->filter(fn (Ticket $ticket) => $ticket->assignedStaff !== null && $ticket->feedback !== null)
            ->groupBy(fn (Ticket $ticket) => $ticket->assignedStaff?->name ?? 'Unassigned')
            ->map(function (Collection $group, string $name) {
                $ratings = $group->map(function (Ticket $ticket) {
                    return collect([
                        $ticket->feedback?->timeliness_rating,
                        $ticket->feedback?->completeness_rating,
                        $ticket->feedback?->overall_satisfaction_rating,
                    ])->filter()->avg();
                })->filter(fn ($value) => $value !== null)->values();

                return [
                    'label' => $name,
                    'average_rating' => $ratings->isEmpty() ? null : round($ratings->avg(), 2),
                    'rated_tickets' => $ratings->count(),
                ];
            })
            ->filter(fn (array $row) => $row['average_rating'] !== null)
            ->sortBy('average_rating')
            ->take(5)
            ->values();
    }

    private function customerSatisfactionTrends(Collection $tickets): Collection
    {
        return $this->trendMonths()->map(function (Carbon $month) use ($tickets) {
            $monthKey = $month->format('Y-m');

            $monthTickets = $tickets->filter(fn (Ticket $ticket) => $ticket->feedback?->submitted_at?->format('Y-m') === $monthKey)->values();

            return [
                'label' => $month->format('M Y'),
                'average_rating' => $this->averageRatingForTickets($monthTickets),
                'responses' => $monthTickets->filter(fn (Ticket $ticket) => $ticket->feedback !== null)->count(),
            ];
        })->filter(fn (array $row) => $row['responses'] > 0)->values();
    }

    private function monthlyTicketTrends(Collection $tickets): Collection
    {
        return $this->trendMonths()->map(function (Carbon $month) use ($tickets) {
            $monthKey = $month->format('Y-m');

            return [
                'label' => $month->format('M Y'),
                'total' => $tickets->filter(fn (Ticket $ticket) => $ticket->created_at?->format('Y-m') === $monthKey)->count(),
                'resolved' => $tickets->filter(fn (Ticket $ticket) => $ticket->resolved_at?->format('Y-m') === $monthKey)->count(),
                'closed' => $tickets->filter(fn (Ticket $ticket) => $ticket->closed_at?->format('Y-m') === $monthKey)->count(),
            ];
        })->filter(fn (array $row) => $row['total'] > 0 || $row['resolved'] > 0 || $row['closed'] > 0)->values();
    }

    private function trendMonths(): Collection
    {
        $currentMonth = now()->copy()->startOfMonth();
        $start = $currentMonth->copy()->subMonths(5);
        $deploymentMonth = Carbon::create(2026, 6, 1)->startOfMonth();

        if ($start->lt($deploymentMonth)) {
            $start = $deploymentMonth;
        }

        return collect(range(0, $start->diffInMonths($currentMonth)))
            ->map(fn (int $offset) => $start->copy()->addMonths($offset));
    }

    private function annualTicketTrends(Collection $tickets): Collection
    {
        return $tickets
            ->groupBy(fn (Ticket $ticket) => $ticket->created_at?->format('Y') ?? 'Unknown')
            ->map(function (Collection $group, string $year) {
                return [
                    'label' => $year,
                    'total' => $group->count(),
                    'resolved' => $group->filter(fn (Ticket $ticket) => $ticket->resolved_at !== null)->count(),
                    'closed' => $group->filter(fn (Ticket $ticket) => $ticket->closed_at !== null)->count(),
                ];
            })
            ->sortBy('label')
            ->values();
    }

    private function averageRatingForTickets(Collection $tickets): ?float
    {
        $ratings = $tickets
            ->filter(fn (Ticket $ticket) => $ticket->feedback !== null)
            ->map(fn (Ticket $ticket) => collect([
                $ticket->feedback?->timeliness_rating,
                $ticket->feedback?->completeness_rating,
                $ticket->feedback?->overall_satisfaction_rating,
            ])->filter()->avg())
            ->filter(fn ($value) => $value !== null)
            ->values();

        return $ratings->isEmpty() ? null : round($ratings->avg(), 2);
    }
}
