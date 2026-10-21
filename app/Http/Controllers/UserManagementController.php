<?php

namespace App\Http\Controllers;

use App\Mail\UserAccountCreatedMail;
use App\Models\Role;
use App\Models\User;
use App\Support\AuditService;
use App\Support\OutboundMail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function __construct(private readonly AuditService $auditService, private readonly OutboundMail $outboundMail) {}

    public function index(Request $request): View
    {
        $this->authorizeManagement($request);
        $request->validate(['edit' => ['nullable', 'integer', 'min:1']]);
        $editingUser = $request->filled('edit')
            ? User::query()->with(['assignedRoles', 'managedRole', 'nutritionProfile'])->findOrFail($request->integer('edit'))
            : null;
        if ($editingUser) {
            $this->authorizeTarget($request, $editingUser);
        }
        $users = User::query()->with(['assignedRoles', 'managedRole', 'nutritionProfile'])->orderBy('name');
        if (! $this->isGlobalManager($request) || $request->query('category') === Role::MICRONUTRIENT) {
            $users->withAccessRoles(array_keys(Role::accessRoles(Role::MICRONUTRIENT)));
        }

        return view('admin.users.index', [
            'users' => $users->get(), 'editingUser' => $editingUser,
            'roles' => $this->availableRoles($request)->get()->groupBy('category'),
            'categories' => Role::categories(), 'isGlobalManager' => $this->isGlobalManager($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManagement($request);
        $data = $this->validateUser($request);
        $password = Str::password(12);
        $user = DB::transaction(function () use ($request, $data, $password) {
            $user = User::query()->create([
                'name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null,
                'designation' => $data['designation'] ?? null, 'place_of_work' => $data['place_of_work'] ?? null,
                'role' => 'none', 'active' => $request->boolean('active', true), 'password' => $password,
                'force_password_change' => true, 'password_changed_at' => null,
            ]);
            $user->syncRoles($this->availableRoles($request)->whereIn('code', $data['roles'])->pluck('id')->all());
            $this->auditService->log('user.created', $user, null, $user->load('assignedRoles')->toArray(), $request->user()->id, $request);

            return $user;
        });

        if (! $this->outboundMail->send($user->email, new UserAccountCreatedMail($user, $password))) {
            return back()->with('warning', 'User created, but the login email was not sent. Check the email configuration.');
        }

        return back()->with('status', 'User created successfully. Login details have been emailed to the user.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeManagement($request);
        $this->authorizeTarget($request, $user);
        $data = $this->validateUser($request, $user);
        DB::transaction(function () use ($request, $data, $user) {
            $old = $user->load('assignedRoles')->toArray();
            $user->fill([
                'name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null,
                'designation' => $data['designation'] ?? null, 'place_of_work' => $data['place_of_work'] ?? null,
                'active' => $request->boolean('active'),
            ]);
            $user->syncRoles($this->availableRoles($request)->whereIn('code', $data['roles'])->pluck('id')->all());
            $this->auditService->log('user.updated', $user, $old, $user->load('assignedRoles')->toArray(), $request->user()->id, $request);
        });

        return to_route('admin.users.index', ['edit' => $user->id])->with('status', 'User updated successfully.');
    }

    private function validateUser(Request $request, ?User $user = null): array
    {
        // Accept the former single-role payload while older clients are upgraded.
        if (! $request->has('roles') && $request->has('role')) {
            $request->merge(['roles' => [$request->input('role')]]);
        } elseif ($request->boolean('roles_present') && ! $request->has('roles')) {
            $request->merge(['roles' => []]);
        }
        $codes = is_array($request->input('roles')) ? $request->input('roles') : [];
        $needsWorkDetails = $this->availableRoles($request)->whereIn('code', $codes)->where('category', Role::MICRONUTRIENT)->exists();

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'roles' => ['present', 'array'],
            'roles.*' => ['required', 'string', 'distinct', Rule::in($this->availableRoles($request)->pluck('code')->all())],
            'designation' => [Rule::requiredIf($needsWorkDetails), 'nullable', 'string', 'max:255'],
            'place_of_work' => [Rule::requiredIf($needsWorkDetails), 'nullable', 'string', 'max:255'],
            'active' => ['nullable', 'boolean'],
        ]);
    }

    private function availableRoles(Request $request): Builder
    {
        return Role::query()->when(! $this->isGlobalManager($request), fn ($q) => $q->where('category', Role::MICRONUTRIENT))->orderBy('name');
    }

    private function isGlobalManager(Request $request): bool
    {
        return $request->user()->hasAnyRole([User::ROLE_ICT_ADMIN]);
    }

    private function authorizeManagement(Request $request): void
    {
        abort_unless($request->user()->hasAnyRole([User::ROLE_ICT_ADMIN, User::ROLE_TASK_ADMIN]), 403);
    }

    private function authorizeTarget(Request $request, User $user): void
    {
        abort_unless($this->isGlobalManager($request) || ($user->isNutritionUser() && ! $user->hasGlobalRole()), 403);
    }
}
