<?php

namespace App\Http\Controllers;

use App\Mail\UserAccountCreatedMail;
use App\Models\NutritionTeamUser;
use App\Models\User;
use App\Support\OutboundMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class NutritionUserController extends Controller
{
    public function __construct(private readonly OutboundMail $outboundMail) {}

    public function index(Request $request): View
    {
        $this->authorizeManagement($request);

        return view('admin.nutrition-users.index', [
            'profiles' => NutritionTeamUser::query()->with('user')->orderBy('designation')->get(),
            'roles' => [User::ROLE_TASK_ADMIN => 'Task Admin', User::ROLE_NUTRITION_USER => 'Nutrition Team Member'],
        ]);
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
            'role' => ['required', Rule::in([User::ROLE_TASK_ADMIN, User::ROLE_NUTRITION_USER])],
            'active' => ['nullable', 'boolean'],
        ]);
        $password = Str::password(12);

        $user = DB::transaction(function () use ($validated, $request, $password) {
            $user = User::query()->create([
                'name' => $validated['name'], 'email' => $validated['email'], 'phone' => $validated['phone'] ?? null,
                'role' => $validated['role'], 'active' => $request->boolean('active', true),
                'password' => Hash::make($password), 'force_password_change' => true, 'password_changed_at' => null,
            ]);
            $user->nutritionProfile()->create([
                'designation' => $validated['designation'], 'place_of_work' => $validated['place_of_work'],
            ]);
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
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'designation' => ['required', 'string', 'max:255'],
            'place_of_work' => ['required', 'string', 'max:255'],
            'role' => ['required', Rule::in([User::ROLE_TASK_ADMIN, User::ROLE_NUTRITION_USER])],
            'password' => ['nullable', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'active' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($validated, $request, $user, $nutritionTeamUser) {
            $user->fill([
                'name' => $validated['name'], 'email' => $validated['email'], 'phone' => $validated['phone'] ?? null,
                'role' => $validated['role'], 'active' => $request->boolean('active'),
            ]);
            if (! empty($validated['password'])) {
                $user->password = Hash::make($validated['password']);
                $user->force_password_change = true;
                $user->password_changed_at = null;
            }
            $user->save();
            $nutritionTeamUser->update(['designation' => $validated['designation'], 'place_of_work' => $validated['place_of_work']]);
        });

        return back()->with('status', 'Nutrition team user updated.');
    }

    private function authorizeManagement(Request $request): void
    {
        abort_unless($request->user()->hasAnyRole([User::ROLE_ICT_ADMIN, User::ROLE_TASK_ADMIN]), 403);
    }
}
