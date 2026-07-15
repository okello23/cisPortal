<?php

namespace App\Http\Controllers;

use App\Models\AlisRemoteBackupKeyUpdateReason;
use App\Models\ClosureReason;
use App\Models\Designation;
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
use App\Support\FacilitySyncService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ManagerListController extends Controller
{
    public const LIST_DEFINITIONS = [
        'alis-key-update-reasons' => 'A-LIS Key Update Reasons',
        'systems' => 'Systems',
        'modules' => 'Modules',
        'regions' => 'Regions',
        'facilities' => 'Facilities',
        'departments' => 'Departments',
        'designations' => 'Designations',
        'issue-types' => 'Issue Types',
        'priority-levels' => 'Priority/Impact Levels',
        'ticket-statuses' => 'Ticket Statuses',
        'resolution-categories' => 'Resolution Categories',
        'closure-reasons' => 'Closure Reasons',
    ];

    public function __construct(
        private readonly AuditService $auditService,
        private readonly FacilitySyncService $facilitySyncService,
    )
    {
    }

    public function index(string $list): View
    {
        $this->authorizeUser();
        $this->authorizeListAccess($list);

        if ($list === 'facilities' && request()->query('action') === 'reset_data') {
            DB::transaction(function () {
                Facility::query()->delete();
                Region::query()->delete();
            });
        }

        if ($list === 'facilities' && request()->query('action') === 'sync_irrds') {
            $this->facilitySyncService->syncFromIrrds();
        }

        [$modelClass, $fields, $title] = $this->resolveList($list);
        $recordsQuery = $modelClass::query();

        if ($list === 'facilities') {
            $recordsQuery->orderBy('sort_order')->orderBy('name');
            $recordsQuery->with('region');
        }

        if ($list === 'alis-key-update-reasons') {
            $recordsQuery->orderByDesc('active')->orderBy('name');
        }

        if (! in_array($list, ['facilities', 'alis-key-update-reasons'], true)) {
            $recordsQuery->orderBy('sort_order')->orderBy('name');
        }

        $editingRecord = null;

        if ($list === 'alis-key-update-reasons' && request()->filled('edit')) {
            $editingRecord = $modelClass::query()->findOrFail((int) request()->query('edit'));
        }

        return view('admin.lists.index', [
            'listKey' => $list,
            'title' => $title,
            'records' => in_array($list, ['facilities', 'alis-key-update-reasons'], true)
                ? $recordsQuery->paginate(10)->withQueryString()
                : $recordsQuery->get(),
            'fields' => $fields,
            'systems' => SupportSystem::query()->where('active', true)->orderBy('name')->get(),
            'regions' => Region::query()->where('active', true)->orderBy('name')->get(),
            'editingRecord' => $editingRecord,
        ]);
    }

    public function syncFacilities(Request $request): RedirectResponse
    {
        $this->authorizeUser();

        $result = $this->facilitySyncService->syncFromIrrds();

        $this->auditService->log('facilities.synced', $request->user(), null, $result, Auth::id(), $request);

        return back()->with('status', "Facilities synced from IRRDS. Created {$result['created']}, updated {$result['updated']}, skipped {$result['skipped']}.");
    }

    public function resetFacilityData(Request $request): RedirectResponse
    {
        $this->authorizeUser();

        DB::transaction(function () {
            Facility::query()->delete();
            Region::query()->delete();
        });

        $result = ['facilities_deleted' => true, 'regions_deleted' => true];
        $this->auditService->log('facilities.reset', $request->user(), null, $result, Auth::id(), $request);

        return back()->with('status', 'Facility and region data cleared successfully.');
    }

    public function store(Request $request, string $list): RedirectResponse
    {
        $this->authorizeUser();
        $this->authorizeListAccess($list);

        if ($list === 'facilities' && $request->input('_intent') === 'sync_irrds') {
            return $this->syncFacilities($request);
        }

        if ($list === 'facilities' && $request->input('_intent') === 'reset_data') {
            return $this->resetFacilityData($request);
        }

        [$modelClass, $fields] = $this->resolveList($list);
        $validated = $this->validatePayload($request, $modelClass, $fields);
        $validated['created_by'] = Auth::id();
        $validated['updated_by'] = Auth::id();
        $record = $modelClass::query()->create($validated);
        $this->auditService->log('list.created', $record, null, $record->toArray(), Auth::id(), $request);

        return back()->with('status', 'List item created successfully.');
    }

    public function update(Request $request, string $list, string $id): RedirectResponse
    {
        $this->authorizeUser();
        $this->authorizeListAccess($list);

        if ($list === 'facilities' && $id === 'sync') {
            return $this->syncFacilities($request);
        }

        if ($list === 'facilities' && $id === 'reset') {
            return $this->resetFacilityData($request);
        }

        [$modelClass, $fields] = $this->resolveList($list);
        $record = $modelClass::query()->findOrFail($id);
        $validated = $this->validatePayload($request, $modelClass, $fields, $record);
        $old = $record->toArray();
        $record->fill($validated);
        $record->updated_by = Auth::id();
        $record->save();
        $this->auditService->log('list.updated', $record, $old, $record->toArray(), Auth::id(), $request);

        return back()->with('status', 'List item updated successfully.');
    }

    private function validatePayload(Request $request, string $modelClass, array $fields, ?Model $record = null): array
    {
        if ($modelClass === AlisRemoteBackupKeyUpdateReason::class) {
            return $request->validate([
                'name' => ['required', 'string', 'max:255', Rule::unique('alis_remote_backup_key_update_reasons', 'name')->ignore($record?->getKey())],
                'description' => ['nullable', 'string'],
                'active' => ['nullable', 'boolean'],
            ]) + [
                'active' => $request->boolean('active', true),
            ];
        }

        /** @var Model $model */
        $model = new $modelClass;

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique($model->getTable(), 'code')->ignore($record?->getKey()),
            ],
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
        $validated['code'] = $this->resolveCodeValue($modelClass, $validated['name'], $validated['code'] ?? null, $record);
        $validated['active'] = $request->boolean('active', true);

        return $validated;
    }

    private function resolveCodeValue(string $modelClass, string $name, ?string $code, ?Model $record = null): string
    {
        $baseCode = Str::upper(Str::slug($code ?: $name, ''));
        $baseCode = Str::limit($baseCode, 50, '');

        if ($baseCode === '') {
            $baseCode = 'ITEM';
        }

        /** @var Model $model */
        $model = new $modelClass;
        $table = $model->getTable();
        $candidate = $baseCode;
        $suffix = 1;

        while (DB::table($table)
            ->where('code', $candidate)
            ->when($record, fn ($query) => $query->where('id', '!=', $record->getKey()))
            ->exists()) {
            $numericSuffix = (string) $suffix;
            $trimmedBase = Str::limit($baseCode, 50 - strlen($numericSuffix), '');
            $candidate = $trimmedBase.$numericSuffix;
            $suffix++;
        }

        return $candidate;
    }

    private function resolveList(string $list): array
    {
        return match ($list) {
            'alis-key-update-reasons' => [AlisRemoteBackupKeyUpdateReason::class, ['name', 'description', 'active'], 'A-LIS Key Update Reasons'],
            'systems' => [SupportSystem::class, ['name', 'code', 'description', 'sort_order', 'active'], 'Systems'],
            'modules' => [SupportModule::class, ['system_id', 'name', 'code', 'description', 'sort_order', 'active'], 'Modules'],
            'regions' => [Region::class, ['name', 'code', 'description', 'sort_order', 'active'], 'Regions'],
            'facilities' => [Facility::class, ['region_id', 'name', 'code', 'description', 'sort_order', 'active'], 'Facilities'],
            'departments' => [Department::class, ['name', 'code', 'description', 'sort_order', 'active'], 'Departments'],
            'designations' => [Designation::class, ['name', 'code', 'description', 'sort_order', 'active'], 'Designations'],
            'issue-types' => [IssueType::class, ['name', 'code', 'description', 'sort_order', 'active'], 'Issue Types'],
            'priority-levels' => [PriorityLevel::class, ['name', 'code', 'sla_hours', 'description', 'sort_order', 'active'], 'Priority Levels'],
            'ticket-statuses' => [TicketStatus::class, ['name', 'code', 'color', 'sort_order', 'active'], 'Ticket Statuses'],
            'resolution-categories' => [ResolutionCategory::class, ['name', 'code', 'description', 'sort_order', 'active'], 'Resolution Categories'],
            'closure-reasons' => [ClosureReason::class, ['name', 'code', 'description', 'sort_order', 'active'], 'Closure Reasons'],
            default => abort(404),
        };
    }

    public static function availableListsForUser(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        if ($user->role === User::ROLE_ICT_ADMIN) {
            return self::LIST_DEFINITIONS;
        }

        if ($user->role === User::ROLE_ICT_SUPERVISOR) {
            return array_intersect_key(self::LIST_DEFINITIONS, array_flip([
                'alis-key-update-reasons',
                'systems',
                'modules',
                'issue-types',
                'priority-levels',
                'ticket-statuses',
                'resolution-categories',
                'closure-reasons',
            ]));
        }

        return [];
    }

    private function authorizeUser(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['ict_admin', 'ict_supervisor']), 403);
    }

    private function authorizeListAccess(string $list): void
    {
        abort_unless(array_key_exists($list, self::availableListsForUser(Auth::user())), 403);
    }
}
