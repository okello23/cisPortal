<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PublicDashboardController extends Controller
{
    public function home(): View
    {
        return view('home', ['stats' => $this->stats()]);
    }

    public function index(): View
    {
        $stats = $this->stats();

        return view('dashboard.public', [
            'stats' => $stats,
            'bySystem' => Ticket::query()
                ->select('support_systems.name', DB::raw('count(*) as total'))
                ->join('support_systems', 'support_systems.id', '=', 'tickets.system_id')
                ->groupBy('support_systems.name')
                ->orderByDesc('total')
                ->get(),
            'byRegion' => Ticket::query()
                ->select('regions.name', DB::raw('count(*) as total'))
                ->leftJoin('regions', 'regions.id', '=', 'tickets.region_id')
                ->groupBy('regions.name')
                ->orderByDesc('total')
                ->get(),
            'byFacility' => Ticket::query()
                ->select('facilities.name', DB::raw('count(*) as total'))
                ->leftJoin('facilities', 'facilities.id', '=', 'tickets.facility_id')
                ->groupBy('facilities.name')
                ->orderByDesc('total')
                ->limit(10)
                ->get(),
            'commonIssues' => Ticket::query()
                ->select('issue_types.name', DB::raw('count(*) as total'))
                ->join('issue_types', 'issue_types.id', '=', 'tickets.issue_type_id')
                ->groupBy('issue_types.name')
                ->orderByDesc('total')
                ->limit(10)
                ->get(),
            'repeatIssues' => Ticket::query()
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

    private function stats(): array
    {
        $resolvedCodes = ['resolved', 'closed'];
        $averageResolutionQuery = match (DB::getDriverName()) {
            'sqlite' => 'AVG((julianday(resolved_at) - julianday(created_at)) * 24)',
            default => 'AVG(EXTRACT(EPOCH FROM (resolved_at - created_at)) / 3600)',
        };

        return [
            'total' => Ticket::query()->count(),
            'open' => Ticket::query()->whereHas('status', fn ($query) => $query->whereNotIn('code', $resolvedCodes))->count(),
            'resolved' => Ticket::query()->whereHas('status', fn ($query) => $query->where('code', 'resolved'))->count(),
            'closed' => Ticket::query()->whereHas('status', fn ($query) => $query->where('code', 'closed'))->count(),
            'overdue' => Ticket::query()->whereDate('expected_resolution_date', '<', now()->toDateString())->whereHas('status', fn ($query) => $query->whereNotIn('code', $resolvedCodes))->count(),
            'today' => Ticket::query()->whereDate('created_at', now()->toDateString())->count(),
            'average_resolution_hours' => round((float) Ticket::query()
                ->whereNotNull('resolved_at')
                ->selectRaw($averageResolutionQuery.' as avg_hours')
                ->value('avg_hours'), 1),
        ];
    }
}
