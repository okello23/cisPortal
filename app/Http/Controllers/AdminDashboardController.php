<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();
        $query = Ticket::query()->with(['status', 'assignedStaff', 'system']);

        if ($user->role === 'ict_support_staff') {
            $query->where('assigned_to', $user->id);
        }

        return view('admin.dashboard', [
            'tickets' => $query->latest()->limit(12)->get(),
            'staff' => User::query()->where('active', true)->orderBy('name')->get(),
            'metrics' => [
                'assigned' => Ticket::query()->whereNotNull('assigned_to')->count(),
                'in_progress' => Ticket::query()->whereHas('status', fn ($status) => $status->where('code', 'in_progress'))->count(),
                'resolved' => Ticket::query()->whereHas('status', fn ($status) => $status->where('code', 'resolved'))->count(),
                'sla_compliance' => Ticket::query()
                    ->whereNotNull('resolved_at')
                    ->whereRaw('date(resolved_at) <= expected_resolution_date')
                    ->count(),
            ],
        ]);
    }
}
