<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Support\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RoleManagementController extends Controller
{
    public function __construct(private readonly AuditService $auditService) {}

    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);

        return view('admin.roles.index', [
            'roles' => Role::query()->withCount('users')->orderBy('category')->orderBy('name')->get(),
            'categories' => Role::categories(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $validated = $this->validateRole($request);
        $validated['code'] = 'custom_'.Str::uuid();
        $role = Role::query()->create($validated);
        $this->auditService->log('role.created', $role, null, $role->toArray(), $request->user()->id, $request);

        return back()->with('status', 'Role created successfully.');
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $validated = $this->validateRole($request, $role);
        if (($role->isBuiltIn() || $role->users()->exists() || User::query()->where('managed_role_id', $role->id)->exists()) &&
            ($validated['category'] !== $role->category || $validated['access_role'] !== $role->access_role)) {
            throw ValidationException::withMessages(['category' => 'Built-in roles and roles assigned to users must keep their category and access level.']);
        }
        $old = $role->toArray();
        $role->update($validated);
        $this->auditService->log('role.updated', $role, $old, $role->toArray(), $request->user()->id, $request);

        return back()->with('status', 'Role updated successfully.');
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        $this->authorizeAdmin($request);
        if ($role->isBuiltIn() || $role->users()->exists() || User::query()->where('managed_role_id', $role->id)->exists()) {
            throw ValidationException::withMessages(['role' => 'Built-in roles and roles assigned to users cannot be deleted.']);
        }
        $this->auditService->log('role.deleted', $role, $role->toArray(), null, $request->user()->id, $request);
        $role->delete();

        return back()->with('status', 'Role deleted successfully.');
    }

    private function validateRole(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($role?->id)],
            'category' => ['required', Rule::in(array_keys(Role::categories()))],
            'access_role' => ['required', Rule::in(array_keys(Role::accessRoles((string) $request->input('category'))))],
        ]);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->hasAnyRole([User::ROLE_ICT_ADMIN]), 403);
    }
}
