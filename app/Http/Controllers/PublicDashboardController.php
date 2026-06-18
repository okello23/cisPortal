<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use App\Models\SupportSystem;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PublicDashboardController extends Controller
{
    public function home(): View
    {
        return view('home', ['stats' => $this->homeStats()]);
    }

    public function index(Request $request): View
    {
        $todayStats = $this->dailyStats();
        $filteredQuery = $this->filteredTicketsQuery($request);
        $stats = $this->filteredStats(clone $filteredQuery);

        return view('dashboard.public', [
            'todayStats' => $todayStats,
            'stats' => $stats,
            'periodOptions' => $this->periodOptions(),
            'systems' => SupportSystem::query()->where('active', true)->orderBy('name')->get(),
            'facilities' => Facility::query()->where('active', true)->orderBy('name')->get(),
            'selectedDate' => now()->format('l, d F Y'),
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
            'total' => (clone $query)->count(),
            'open' => (clone $query)->whereHas('status', fn ($status) => $status->whereNotIn('code', $resolvedCodes))->count(),
            'resolved' => (clone $query)->whereHas('status', fn ($status) => $status->where('code', 'resolved'))->count(),
            'closed' => (clone $query)->whereHas('status', fn ($status) => $status->where('code', 'closed'))->count(),
            'overdue' => (clone $query)->whereDate('expected_resolution_date', '<', now()->toDateString())->whereHas('status', fn ($status) => $status->whereNotIn('code', $resolvedCodes))->count(),
            'this_month' => (clone $query)->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            'facilities' => (clone $query)->distinct('facility_id')->whereNotNull('facility_id')->count('facility_id'),
            'systems' => (clone $query)->distinct('system_id')->count('system_id'),
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

        [$from, $to] = $this->periodRange($request->string('period')->toString() ?: 'all_time');

        if ($from && $to) {
            $query->whereBetween('created_at', [$from, $to]);
        }

        return $query;
    }

    private function periodRange(string $period): array
    {
        $now = now();

        return match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'this_week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last_30_days' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'this_year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
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
            'last_30_days' => 'Last 30 Days',
            'this_year' => 'This Year',
        ];
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
