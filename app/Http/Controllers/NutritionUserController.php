<?php

namespace App\Http\Controllers;

use App\Mail\UserAccountCreatedMail;
use App\Models\NutritionTeamUser;
use App\Models\Role;
use App\Models\User;
use App\Support\OutboundMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class NutritionUserController extends Controller
{
    public function __construct(private readonly OutboundMail $outboundMail) {}

    public function index(Request $request): RedirectResponse
    {
        $this->authorizeManagement($request);

        $request->validate(['edit' => ['nullable', 'integer', 'min:1']]);
        $editingProfile = $request->filled('edit')
            ? NutritionTeamUser::query()->with('user.managedRole')->findOrFail($request->integer('edit'))
            : null;

        return to_route('admin.users.index', array_filter([
            'category' => Role::MICRONUTRIENT, 'edit' => $editingProfile?->user_id,
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManagement($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'designation' => ['required', 'string', 'max:255'],
            'place_of_work' => ['required', 'string', 'max:255'],
            'role' => ['required', Rule::in(array_keys(Role::choices(Role::MICRONUTRIENT)))],
            'active' => ['nullable', 'boolean'],
        ]);
        $password = Str::password(12);
        $role = Role::query()->where('category', Role::MICRONUTRIENT)->where('code', $validated['role'])->firstOrFail();

        $user = DB::transaction(function () use ($validated, $request, $password, $role) {
            $user = User::query()->create([
                'name' => $validated['name'], 'email' => $validated['email'], 'phone' => $validated['phone'] ?? null,
                'role' => $role->access_role, 'managed_role_id' => $role->id, 'active' => $request->boolean('active', true),
                'designation' => $validated['designation'], 'place_of_work' => $validated['place_of_work'],
                'password' => Hash::make($password), 'force_password_change' => true, 'password_changed_at' => null,
            ]);
            $user->syncRoles([$role->id]);

            return $user;
        });

        if (! $this->outboundMail->send($user->email, new UserAccountCreatedMail($user, $password))) {
            return back()->with('warning', 'Nutrition team user created, but the login email was not sent. Configure SMTP and set a new password for this user before asking them to sign in.');
        }

        return back()->with('status', 'Nutrition team user created. Login details have been sent to their email address.');
    }

    public function update(Request $request, NutritionTeamUser $nutritionTeamUser): RedirectResponse
    {
        $this->authorizeManagement($request);
        $user = $nutritionTeamUser->user;
        abort_unless($request->user()->hasAnyRole([User::ROLE_ICT_ADMIN]) || ! $user->hasGlobalRole(), 403);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'designation' => ['required', 'string', 'max:255'],
            'place_of_work' => ['required', 'string', 'max:255'],
            'role' => ['required', Rule::in(array_keys(Role::choices(Role::MICRONUTRIENT)))],
            'active' => ['nullable', 'boolean'],
        ]);

        $role = Role::query()->where('category', Role::MICRONUTRIENT)->where('code', $validated['role'])->firstOrFail();
        DB::transaction(function () use ($validated, $request, $user, $nutritionTeamUser, $role) {
            $user->fill([
                'name' => $validated['name'], 'email' => $validated['email'], 'phone' => $validated['phone'] ?? null,
                'role' => $role->access_role, 'managed_role_id' => $role->id, 'active' => $request->boolean('active'),
                'designation' => $validated['designation'], 'place_of_work' => $validated['place_of_work'],
            ]);
            $globalIds = $user->assignedRoles->where('category', Role::GLOBAL)->modelKeys();
            $user->syncRoles([...$globalIds, $role->id]);
            $nutritionTeamUser->update(['designation' => $validated['designation'], 'place_of_work' => $validated['place_of_work']]);
        });

        return to_route('admin.nutrition-users.index', ['edit' => $nutritionTeamUser->id])
            ->with('status', 'Nutrition team user updated.');
    }

    private function authorizeManagement(Request $request): void
    {
        abort_unless($request->user()->hasAnyRole([User::ROLE_ICT_ADMIN, User::ROLE_TASK_ADMIN]), 403);
    }
}
