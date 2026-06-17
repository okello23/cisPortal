<?php

namespace App\Http\Controllers;

use App\Models\ClosureReason;
use App\Models\Department;
use App\Models\Facility;
use App\Models\IssueType;
use App\Models\PriorityLevel;
use App\Models\Region;
use App\Models\ResolutionCategory;
use App\Models\SupportModule;
use App\Models\SupportSystem;
use App\Models\TicketStatus;
use App\Support\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ManagerListController extends Controller
{
    public function __construct(private readonly AuditService $auditService)
    {
    }

    public function index(string $list): View
    {
        $this->authorizeUser();
        [$modelClass, $fields, $title] = $this->resolveList($list);

        return view('admin.lists.index', [
            'listKey' => $list,
            'title' => $title,
            'records' => $modelClass::query()->orderBy('sort_order')->orderBy('name')->get(),
            'fields' => $fields,
            'systems' => SupportSystem::query()->where('active', true)->orderBy('name')->get(),
            'regions' => Region::query()->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, string $list): RedirectResponse
    {
        $this->authorizeUser();
        [$modelClass, $fields] = $this->resolveList($list);
        $validated = $this->validatePayload($request, $fields);
        $validated['created_by'] = Auth::id();
        $validated['updated_by'] = Auth::id();
        $record = $modelClass::query()->create($validated);
        $this->auditService->log('list.created', $record, null, $record->toArray(), Auth::id(), $request);

        return back()->with('status', 'List item created successfully.');
    }

    public function update(Request $request, string $list, int $id): RedirectResponse
    {
        $this->authorizeUser();
        [$modelClass, $fields] = $this->resolveList($list);
        $validated = $this->validatePayload($request, $fields);
        $record = $modelClass::query()->findOrFail($id);
        $old = $record->toArray();
        $record->fill($validated);
        $record->updated_by = Auth::id();
        $record->save();
        $this->auditService->log('list.updated', $record, $old, $record->toArray(), Auth::id(), $request);

        return back()->with('status', 'List item updated successfully.');
    }

    private function validatePayload(Request $request, array $fields): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ];

        if (in_array('sla_hours', $fields, true)) {
            $rules['sla_hours'] = ['nullable', 'integer', 'min:1'];
        }

        if (in_array('color', $fields, true)) {
            $rules['color'] = ['nullable', 'string', 'max:50'];
        }

        if (in_array('system_id', $fields, true)) {
            $rules['system_id'] = ['required', 'exists:support_systems,id'];
        }

        if (in_array('region_id', $fields, true)) {
            $rules['region_id'] = ['nullable', 'exists:regions,id'];
        }

        $validated = $request->validate($rules);
        $validated['active'] = $request->boolean('active', true);

        return $validated;
    }

    private function resolveList(string $list): array
    {
        return match ($list) {
            'systems' => [SupportSystem::class, ['name', 'code', 'description', 'sort_order', 'active'], 'Systems'],
            'modules' => [SupportModule::class, ['system_id', 'name', 'code', 'description', 'sort_order', 'active'], 'Modules'],
            'regions' => [Region::class, ['name', 'code', 'description', 'sort_order', 'active'], 'Regions'],
            'facilities' => [Facility::class, ['region_id', 'name', 'code', 'description', 'sort_order', 'active'], 'Facilities'],
            'departments' => [Department::class, ['name', 'code', 'description', 'sort_order', 'active'], 'Departments'],
            'issue-types' => [IssueType::class, ['name', 'code', 'description', 'sort_order', 'active'], 'Issue Types'],
            'priority-levels' => [PriorityLevel::class, ['name', 'code', 'sla_hours', 'description', 'sort_order', 'active'], 'Priority Levels'],
            'ticket-statuses' => [TicketStatus::class, ['name', 'code', 'color', 'sort_order', 'active'], 'Ticket Statuses'],
            'resolution-categories' => [ResolutionCategory::class, ['name', 'code', 'description', 'sort_order', 'active'], 'Resolution Categories'],
            'closure-reasons' => [ClosureReason::class, ['name', 'code', 'description', 'sort_order', 'active'], 'Closure Reasons'],
            default => abort(404),
        };
    }

    private function authorizeUser(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['ict_admin', 'ict_supervisor']), 403);
    }
}
