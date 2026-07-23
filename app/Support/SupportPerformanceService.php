<?php

namespace App\Support;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketStatusLog;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Support\Collection;

class SupportPerformanceService
{
    public function buildForUser(User $viewer, array $filters = []): array
    {
        $staff = $this->staffScope($viewer, $filters);

        if ($staff->isEmpty()) {
            return [
                'is_manager_scope' => $this->isManager($viewer),
                'viewer' => $viewer,
                'rows' => collect(),
                'summary' => $this->summary(collect()),
                'months' => $this->trendMonths(),
                'filters' => [
                    'staff_id' => $filters['staff_id'] ?? null,
                ],
                'filter_options' => [
                    'staff' => $this->staffFilterOptions($viewer),
                ],
            ];
        }

        $tickets = Ticket::query()
            ->with(['status', 'priorityLevel', 'feedback'])
            ->whereIn('assigned_to', $staff->pluck('id'))
            ->whereNotIn('submission_review_status', ['QUARANTINED', 'REJECTED_SPAM'])
            ->get();

        $ticketIds = $tickets->pluck('id')->all();

        $comments = TicketComment::query()
            ->whereIn('ticket_id', $ticketIds)
            ->whereIn('created_by', $staff->pluck('id'))
            ->orderBy('created_at')
            ->get();

        $statusLogs = TicketStatusLog::query()
            ->with('newStatus:id,code,name')
            ->whereIn('ticket_id', $ticketIds)
            ->whereIn('changed_by', $staff->pluck('id'))
            ->orderBy('created_at')
            ->get();

        $reopenedStatusId = TicketStatus::query()->where('code', 'reopened')->value('id');
        $escalatedStatusId = TicketStatus::query()->where('code', 'escalated')->value('id');
        $reopenedTicketIds = $reopenedStatusId
            ? TicketStatusLog::query()
                ->whereIn('ticket_id', $ticketIds)
                ->where('new_status_id', $reopenedStatusId)
                ->pluck('ticket_id')
                ->unique()
            : collect();

        $rows = $staff->map(function (User $staffMember) use ($tickets, $comments, $statusLogs, $reopenedTicketIds, $escalatedStatusId) {
            $assignedTickets = $tickets->where('assigned_to', $staffMember->id)->values();
            $assignedTicketIds = $assignedTickets->pluck('id');
            $staffComments = $comments->where('created_by', $staffMember->id)->whereIn('ticket_id', $assignedTicketIds);
            $staffStatusLogs = $statusLogs->where('changed_by', $staffMember->id)->whereIn('ticket_id', $assignedTicketIds);

            $resolvedTickets = $assignedTickets->filter(fn (Ticket $ticket) => $ticket->status?->code === 'resolved')->values();
            $resolvedAndClosedTickets = $assignedTickets->filter(fn (Ticket $ticket) => in_array($ticket->status?->code, ['resolved', 'closed'], true))->values();
            $closedTickets = $assignedTickets->filter(fn (Ticket $ticket) => $ticket->status?->code === 'closed')->values();
            $activeTickets = $assignedTickets->filter(fn (Ticket $ticket) => ! in_array($ticket->status?->code, ['resolved', 'closed'], true))->values();
            $overdueTickets = $activeTickets->filter(function (Ticket $ticket) {
                return $ticket->expected_resolution_date !== null
                    && $ticket->expected_resolution_date->endOfDay()->lt(now());
            })->values();

            $escalatedCount = $staffStatusLogs
                ->where('new_status_id', $escalatedStatusId)
                ->pluck('ticket_id')
                ->unique()
                ->count();

            $reopenedCount = $assignedTicketIds
                ->filter(fn ($ticketId) => $reopenedTicketIds->contains($ticketId))
                ->count();

            $averageFirstResponseHours = $this->averageFirstResponseHours($assignedTickets, $staffComments, $staffStatusLogs);
            $averageResolutionHours = $this->averageResolutionHours($resolvedAndClosedTickets);
            $slaComplianceRate = $this->slaComplianceRate($resolvedAndClosedTickets);
            $averageCustomerRating = $this->averageCustomerRating($assignedTickets);

            return [
                'staff_id' => $staffMember->id,
                'staff_name' => $staffMember->name,
                'role' => User::roleLabels()[$staffMember->role] ?? $staffMember->role,
                'tickets_assigned' => $assignedTickets->count(),
                'tickets_resolved' => $resolvedTickets->count(),
                'tickets_closed' => $closedTickets->count(),
                'tickets_escalated' => $escalatedCount,
                'avg_first_response_hours' => $averageFirstResponseHours,
                'avg_resolution_hours' => $averageResolutionHours,
                'sla_compliance_rate' => $slaComplianceRate,
                'avg_customer_rating' => $averageCustomerRating,
                'reopened_tickets' => $reopenedCount,
                'current_backlog' => $overdueTickets->count(),
                'active_tickets' => $activeTickets->count(),
                'monthly_trends' => $this->monthlyTrends($staffMember, $assignedTickets, $staffStatusLogs),
            ];
        })->values();

        return [
            'is_manager_scope' => $this->isManager($viewer),
            'viewer' => $viewer,
            'rows' => $rows,
            'summary' => $this->summary($rows),
            'months' => $this->trendMonths(),
            'filters' => [
                'staff_id' => $filters['staff_id'] ?? null,
            ],
            'filter_options' => [
                'staff' => $this->staffFilterOptions($viewer),
            ],
        ];
    }

    public function exportRows(array $dataset): Collection
    {
        return collect($dataset['rows'])->map(function (array $row) {
            return [
                'Support Staff' => $row['staff_name'],
                'Role' => $row['role'],
                'Tickets Assigned' => $row['tickets_assigned'],
                'Tickets Resolved' => $row['tickets_resolved'],
                'Tickets Closed' => $row['tickets_closed'],
                'Tickets Escalated' => $row['tickets_escalated'],
                'Average First Response Time (hrs)' => $this->formatHours($row['avg_first_response_hours']),
                'Average Resolution Time (hrs)' => $this->formatHours($row['avg_resolution_hours']),
                'SLA Compliance Rate (%)' => $this->formatPercentage($row['sla_compliance_rate']),
                'Average Customer Rating' => $this->formatRating($row['avg_customer_rating']),
                'Reopened Tickets' => $row['reopened_tickets'],
                'Current Backlog' => $row['current_backlog'],
                'Active Tickets' => $row['active_tickets'],
            ];
        });
    }

    public function formatHours(?float $value): string
    {
        return $value === null ? 'N/A' : number_format($value, 1);
    }

    public function formatPercentage(?float $value): string
    {
        return $value === null ? 'N/A' : number_format($value, 1);
    }

    public function formatRating(?float $value): string
    {
        return $value === null ? 'N/A' : number_format($value, 1);
    }

    private function isManager(User $viewer): bool
    {
        return $viewer->hasAnyRole([
            User::ROLE_ICT_ADMIN,
            User::ROLE_ICT_MANAGER,
            User::ROLE_ICT_SUPERVISOR,
        ]);
    }

    private function staffScope(User $viewer, array $filters = []): Collection
    {
        if ($this->isManager($viewer)) {
            return User::query()
                ->where('active', true)
                ->where('role', User::ROLE_ICT_SUPPORT_STAFF)
                ->when(
                    ! empty($filters['staff_id']),
                    fn ($query) => $query->whereKey((int) $filters['staff_id'])
                )
                ->orderBy('name')
                ->get(['id', 'name', 'role']);
        }

        return collect([$viewer]);
    }

    private function staffFilterOptions(User $viewer): Collection
    {
        if (! $this->isManager($viewer)) {
            return collect([$viewer])->map(fn (User $user) => (object) [
                'id' => $user->id,
                'name' => $user->name,
            ]);
        }

        return User::query()
            ->where('active', true)
            ->where('role', User::ROLE_ICT_SUPPORT_STAFF)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function averageFirstResponseHours(Collection $tickets, Collection $comments, Collection $statusLogs): ?float
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

            $firstTouch = collect([$firstCommentAt, $firstStatusAt])
                ->filter()
                ->sort()
                ->first();

            return $firstTouch ? $ticket->assigned_at->diffInSeconds($firstTouch) / 3600 : null;
        })->filter(fn ($value) => $value !== null)->values();

        return $hours->isEmpty() ? null : round($hours->avg(), 2);
    }

    private function averageResolutionHours(Collection $resolvedTickets): ?float
    {
        $hours = $resolvedTickets
            ->filter(fn (Ticket $ticket) => $ticket->assigned_at !== null && $ticket->resolved_at !== null)
            ->map(fn (Ticket $ticket) => $ticket->assigned_at->diffInSeconds($ticket->resolved_at) / 3600)
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

    private function averageCustomerRating(Collection $tickets): ?float
    {
        $ratings = $tickets
            ->filter(fn (Ticket $ticket) => $ticket->feedback !== null)
            ->map(function (Ticket $ticket) {
                return collect([
                    $ticket->feedback->timeliness_rating,
                    $ticket->feedback->completeness_rating,
                    $ticket->feedback->overall_satisfaction_rating,
                ])->avg();
            })
            ->values();

        return $ratings->isEmpty() ? null : round($ratings->avg(), 2);
    }

    private function monthlyTrends(User $staffMember, Collection $tickets, Collection $statusLogs): array
    {
        $months = $this->trendMonths();

        return collect($months)->map(function (array $month) use ($staffMember, $tickets, $statusLogs) {
            $monthKey = $month['key'];

            $assignedCount = $tickets
                ->filter(fn (Ticket $ticket) => $ticket->assigned_at?->format('Y-m') === $monthKey)
                ->count();

            $resolvedTicketIds = $statusLogs
                ->filter(fn (TicketStatusLog $log) => $log->newStatus?->code === 'resolved' && $log->created_at?->format('Y-m') === $monthKey)
                ->pluck('ticket_id')
                ->unique();

            $closedTicketIds = $statusLogs
                ->filter(fn (TicketStatusLog $log) => $log->newStatus?->code === 'closed' && $log->created_at?->format('Y-m') === $monthKey)
                ->pluck('ticket_id')
                ->unique();

            $monthlyRatedTickets = $tickets
                ->filter(function (Ticket $ticket) use ($monthKey, $staffMember) {
                    return $ticket->assigned_to === $staffMember->id
                        && $ticket->feedback?->submitted_at?->format('Y-m') === $monthKey;
                })
                ->values();

            return [
                'label' => $month['label'],
                'assigned' => $assignedCount,
                'resolved' => $resolvedTicketIds->count(),
                'closed' => $closedTicketIds->count(),
                'rating' => $this->averageCustomerRating($monthlyRatedTickets),
            ];
        })->all();
    }

    private function trendMonths(): array
    {
        $start = now()->copy()->startOfMonth()->subMonths(5);

        return collect(range(0, 5))->map(function (int $offset) use ($start) {
            $month = $start->copy()->addMonths($offset);

            return [
                'key' => $month->format('Y-m'),
                'label' => $month->format('M Y'),
            ];
        })->all();
    }

    private function summary(Collection $rows): array
    {
        return [
            'staff_count' => $rows->count(),
            'tickets_assigned' => $rows->sum('tickets_assigned'),
            'tickets_resolved' => $rows->sum('tickets_resolved'),
            'tickets_closed' => $rows->sum('tickets_closed'),
            'active_tickets' => $rows->sum('active_tickets'),
            'current_backlog' => $rows->sum('current_backlog'),
            'avg_sla_compliance_rate' => $rows->avg('sla_compliance_rate'),
            'avg_customer_rating' => $rows->avg('avg_customer_rating'),
        ];
    }
}
