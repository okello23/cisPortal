<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use App\Models\SupportSystem;
use App\Models\Ticket;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PublicDashboardController extends Controller
{
    public function home(): View
    {
        return view('home', [
            'stats' => $this->homeStats(),
            'todayStats' => $this->dailyStats(),
            'overviewStats' => $this->filteredStats(Ticket::query()),
        ]);
    }

    public function index(Request $request): View
    {
        $todayStats = $this->dailyStats();
        $filteredQuery = $this->filteredTicketsQuery($request);
        $stats = $this->filteredStats(clone $filteredQuery);
        $metricCards = $this->metricCards(clone $filteredQuery, $stats);

        return view('dashboard.public', [
            'todayStats' => $todayStats,
            'stats' => $stats,
            'periodOptions' => $this->periodOptions(),
            'systems' => SupportSystem::query()->where('active', true)->orderBy('name')->get(),
            'facilities' => Facility::query()->where('active', true)->orderBy('name')->get(),
            'facilityTypes' => Facility::query()
                ->where('active', true)
                ->whereNotNull('facility_type')
                ->where('facility_type', '!=', '')
                ->orderBy('facility_type')
                ->distinct()
                ->pluck('facility_type'),
            'selectedDate' => now()->format('l, d F Y'),
            'filteredDateLabel' => $this->selectedDateLabel($request),
            'metricCards' => $metricCards,
            'bySystem' => (clone $filteredQuery)
                ->select('support_systems.name', DB::raw('count(*) as total'))
                ->join('support_systems', 'support_systems.id', '=', 'tickets.system_id')
                ->groupBy('support_systems.name')
                ->orderByDesc('total')
                ->get(),
            'byRegion' => (clone $filteredQuery)
                ->select('regions.name', DB::raw('count(*) as total'))
                ->leftJoin('regions', 'regions.id', '=', 'tickets.region_id')
                ->groupBy('regions.name')
                ->orderByDesc('total')
                ->get(),
            'byFacility' => (clone $filteredQuery)
                ->select('facilities.name', DB::raw('count(*) as total'))
                ->leftJoin('facilities', 'facilities.id', '=', 'tickets.facility_id')
                ->groupBy('facilities.name')
                ->orderByDesc('total')
                ->limit(10)
                ->get(),
            'commonIssues' => (clone $filteredQuery)
                ->select('issue_types.name', DB::raw('count(*) as total'))
                ->join('issue_types', 'issue_types.id', '=', 'tickets.issue_type_id')
                ->groupBy('issue_types.name')
                ->orderByDesc('total')
                ->limit(10)
                ->get(),
            'repeatIssues' => (clone $filteredQuery)
                ->select('facilities.name as facility_name', 'issue_types.name as issue_name', DB::raw('count(*) as total'))
                ->leftJoin('facilities', 'facilities.id', '=', 'tickets.facility_id')
                ->join('issue_types', 'issue_types.id', '=', 'tickets.issue_type_id')
                ->groupBy('facilities.name', 'issue_types.name')
                ->havingRaw('count(*) > 1')
                ->orderByDesc('total')
                ->limit(10)
                ->get(),
        ]);
    }

    private function dailyStats(): array
    {
        $query = Ticket::query();
        $today = now()->toDateString();

        return [
            'total' => (clone $query)->whereDate('created_at', $today)->count(),
            'resolved' => (clone $query)->whereDate('resolved_at', $today)->count(),
            'closed' => (clone $query)->whereDate('closed_at', $today)->count(),
            'open' => (clone $query)->whereDate('created_at', $today)->whereHas('status', fn ($status) => $status->whereNotIn('code', ['resolved', 'closed']))->count(),
            'facilities' => (clone $query)->whereDate('created_at', $today)->distinct('facility_id')->whereNotNull('facility_id')->count('facility_id'),
        ];
    }

    private function homeStats(): array
    {
        return [
            'total' => Ticket::query()->count(),
            'open' => Ticket::query()->whereHas('status', fn ($status) => $status->whereNotIn('code', ['resolved', 'closed']))->count(),
            'resolved' => Ticket::query()->whereHas('status', fn ($status) => $status->where('code', 'resolved'))->count(),
            'today' => Ticket::query()->whereDate('created_at', now()->toDateString())->count(),
        ];
    }

    private function filteredStats(Builder $query): array
    {
        $resolvedCodes = ['resolved', 'closed'];

        return [
            'total_logged' => (clone $query)->count(),
            'assigned' => (clone $query)->whereNotNull('assigned_to')->count(),
            'pending_assignment' => (clone $query)
                ->whereNull('assigned_to')
                ->whereHas('status', fn ($status) => $status->whereNotIn('code', $resolvedCodes))
                ->count(),
            'resolved' => (clone $query)->whereHas('status', fn ($status) => $status->where('code', 'resolved'))->count(),
            'closed' => (clone $query)->whereHas('status', fn ($status) => $status->where('code', 'closed'))->count(),
            'escalated' => (clone $query)->whereHas('status', fn ($status) => $status->where('code', 'escalated'))->count(),
            'facilities_reporting' => (clone $query)->distinct('facility_id')->whereNotNull('facility_id')->count('facility_id'),
            'average_resolution_hours' => round((float) (clone $query)
                ->whereNotNull('resolved_at')
                ->selectRaw($this->averageResolutionExpression().' as avg_hours')
                ->value('avg_hours'), 1),
        ];
    }

    private function filteredTicketsQuery(Request $request): Builder
    {
        $query = Ticket::query();

        if ($request->filled('system_id')) {
            $query->where('system_id', $request->integer('system_id'));
        }

        if ($request->filled('facility_id')) {
            $query->where('facility_id', $request->integer('facility_id'));
        }

        if ($request->filled('facility_type')) {
            $facilityType = $request->string('facility_type')->trim()->toString();
            $query->whereHas('facility', fn (Builder $facilityQuery) => $facilityQuery->where('facility_type', $facilityType));
        }

        [$from, $to] = $this->periodRange($request);

        if ($from && $to) {
            $query->whereBetween('created_at', [$from, $to]);
        }

        return $query;
    }

    private function periodRange(Request $request): array
    {
        $now = now();
        $period = $request->string('period')->toString() ?: 'all_time';

        return match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'this_week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'this_year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'custom' => $this->customDateRange($request),
            default => [null, null],
        };
    }

    private function periodOptions(): array
    {
        return [
            'all_time' => 'All Time',
            'today' => 'Today',
            'this_week' => 'This Week',
            'this_month' => 'This Month',
            'this_year' => 'This Year',
            'custom' => 'Custom Date Range',
        ];
    }

    private function customDateRange(Request $request): array
    {
        if (! $request->filled('start_date') || ! $request->filled('end_date')) {
            return [null, null];
        }

        $start = Carbon::parse($request->string('start_date')->toString())->startOfDay();
        $end = Carbon::parse($request->string('end_date')->toString())->endOfDay();

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        return [$start, $end];
    }

    private function selectedDateLabel(Request $request): string
    {
        $period = $request->string('period')->toString() ?: 'all_time';

        return match ($period) {
            'today' => 'Today only',
            'this_week' => 'This week',
            'this_month' => 'This month',
            'this_year' => 'This year',
            'custom' => $this->customDateLabel($request),
            default => 'All time',
        };
    }

    private function customDateLabel(Request $request): string
    {
        if (! $request->filled('start_date') || ! $request->filled('end_date')) {
            return 'Custom date range';
        }

        $start = Carbon::parse($request->string('start_date')->toString());
        $end = Carbon::parse($request->string('end_date')->toString());

        if ($start->isSameDay($end)) {
            return $start->format('l, d F Y');
        }

        return $start->format('d M Y').' to '.$end->format('d M Y');
    }

    private function metricCards(Builder $query, array $stats): array
    {
        return [
            [
                'key' => 'total_logged',
                'label' => 'Total Tickets Logged',
                'value' => $stats['total_logged'],
                'hover_text' => 'Open to view the most recent tickets captured in the selected date range and facility filters.',
                'modal_title' => 'Total Tickets Logged',
                'modal_description' => 'Recent tickets that match the current dashboard filters.',
                'type' => 'tickets',
                'rows' => $this->ticketRows(clone $query),
            ],
            [
                'key' => 'assigned',
                'label' => 'Assigned Tickets',
                'value' => $stats['assigned'],
                'hover_text' => 'Open to view tickets that have already been assigned to a staff member.',
                'modal_title' => 'Assigned Tickets',
                'modal_description' => 'Tickets with an assigned staff member under the current filters.',
                'type' => 'tickets',
                'rows' => $this->ticketRows((clone $query)->whereNotNull('assigned_to')),
            ],
            [
                'key' => 'pending_assignment',
                'label' => 'Pending Assignment',
                'value' => $stats['pending_assignment'],
                'hover_text' => 'Open to view tickets still waiting for ownership so users know these have not yet been assigned.',
                'modal_title' => 'Pending Assignment',
                'modal_description' => 'Tickets that are still unassigned and not yet resolved or closed.',
                'type' => 'tickets',
                'rows' => $this->ticketRows(
                    (clone $query)
                        ->whereNull('assigned_to')
                        ->whereHas('status', fn ($status) => $status->whereNotIn('code', ['resolved', 'closed']))
                ),
            ],
            [
                'key' => 'resolved',
                'label' => 'Resolved',
                'value' => $stats['resolved'],
                'hover_text' => 'Open to review tickets marked as resolved in the selected period.',
                'modal_title' => 'Resolved Tickets',
                'modal_description' => 'Tickets whose current status is resolved.',
                'type' => 'tickets',
                'rows' => $this->ticketRows((clone $query)->whereHas('status', fn ($status) => $status->where('code', 'resolved'))),
            ],
            [
                'key' => 'closed',
                'label' => 'Closed',
                'value' => $stats['closed'],
                'hover_text' => 'Open to review tickets that have been fully closed out.',
                'modal_title' => 'Closed Tickets',
                'modal_description' => 'Tickets whose current status is closed.',
                'type' => 'tickets',
                'rows' => $this->ticketRows((clone $query)->whereHas('status', fn ($status) => $status->where('code', 'closed'))),
            ],
            [
                'key' => 'escalated',
                'label' => 'Tickets Escalated to Devs',
                'value' => $stats['escalated'],
                'hover_text' => 'Open to inspect tickets that have been escalated to developers or higher technical support.',
                'modal_title' => 'Tickets Escalated to Devs',
                'modal_description' => 'Tickets currently marked as escalated.',
                'type' => 'tickets',
                'rows' => $this->ticketRows((clone $query)->whereHas('status', fn ($status) => $status->where('code', 'escalated'))),
            ],
            [
                'key' => 'facilities_reporting',
                'label' => 'Facilities With Logged Tickets',
                'value' => $stats['facilities_reporting'],
                'hover_text' => 'Open to see which facilities have logged tickets and how many each has submitted.',
                'modal_title' => 'Facilities With Logged Tickets',
                'modal_description' => 'Facilities represented in the current filtered ticket set.',
                'type' => 'facilities',
                'rows' => $this->facilityRows(clone $query),
            ],
        ];
    }

    private function ticketRows(Builder $query): array
    {
        return (clone $query)
            ->with(['system:id,name', 'facility:id,name,facility_type', 'status:id,name,code', 'assignedStaff:id,name'])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn (Ticket $ticket) => [
                'ticket_number' => $ticket->ticket_number,
                'system' => $ticket->system?->name ?? 'Unspecified',
                'facility' => $ticket->facility?->name ?? 'Unspecified',
                'facility_type' => $ticket->facility?->facility_type ?? 'Unspecified',
                'status' => $ticket->status?->name ?? 'Unspecified',
                'assigned_to' => $ticket->assignedStaff?->name ?? 'Unassigned',
                'reported_at' => optional($ticket->created_at)->format('d M Y H:i'),
            ])
            ->all();
    }

    private function facilityRows(Builder $query): array
    {
        return (clone $query)
            ->leftJoin('facilities', 'facilities.id', '=', 'tickets.facility_id')
            ->leftJoin('regions', 'regions.id', '=', 'tickets.region_id')
            ->select(
                'facilities.name as facility_name',
                'facilities.facility_type',
                'regions.name as region_name',
                DB::raw('count(*) as total')
            )
            ->whereNotNull('tickets.facility_id')
            ->groupBy('facilities.name', 'facilities.facility_type', 'regions.name')
            ->orderByDesc('total')
            ->limit(20)
            ->get()
            ->map(fn ($row) => [
                'facility' => $row->facility_name ?? 'Unspecified',
                'facility_type' => $row->facility_type ?? 'Unspecified',
                'region' => $row->region_name ?? 'Unspecified',
                'total' => $row->total,
            ])
            ->all();
    }

    private function averageResolutionExpression(): string
    {
        return match (DB::getDriverName()) {
            'sqlite' => 'AVG((julianday(resolved_at) - julianday(created_at)) * 24)',
            'mysql' => 'AVG(TIMESTAMPDIFF(SECOND, created_at, resolved_at) / 3600)',
            'pgsql' => 'AVG(EXTRACT(EPOCH FROM (resolved_at - created_at)) / 3600)',
            default => 'AVG(TIMESTAMPDIFF(SECOND, created_at, resolved_at) / 3600)',
        };
    }
}
