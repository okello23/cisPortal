<?php

namespace App\Http\Controllers;

use App\Mail\UserAccountCreatedMail;
use App\Models\User;
use App\Support\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function __construct(private readonly AuditService $auditService)
    {
    }

    public function index(): View
    {
        $this->authorizeAdmin();

        return view('admin.users.index', [
            'users' => User::query()->orderBy('name')->get(),
            'roles' => $this->roles(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'role' => ['required', Rule::in(array_keys($this->roles()))],
            'active' => ['nullable', 'boolean'],
        ]);

        $generatedPassword = Str::password(12);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'role' => $validated['role'],
            'active' => $request->boolean('active', true),
            'password' => Hash::make($generatedPassword),
        ]);

        $this->auditService->log('user.created', $user, null, $user->toArray(), Auth::id(), $request);

        Mail::to($user->email)->send(new UserAccountCreatedMail($user, $generatedPassword));

        return back()->with('status', 'User created successfully. Login details have been emailed to the user.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'role' => ['required', Rule::in(array_keys($this->roles()))],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'active' => ['nullable', 'boolean'],
        ]);

        $old = $user->toArray();

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'role' => $validated['role'],
            'active' => $request->boolean('active'),
        ]);

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        $this->auditService->log('user.updated', $user, $old, $user->toArray(), Auth::id(), $request);

        return back()->with('status', 'User updated successfully.');
    }

    private function authorizeAdmin(): void
    {
        abort_unless(Auth::user()->role === 'ict_admin', 403);
    }

    private function roles(): array
    {
        return [
            'ict_admin' => 'ICT Admin',
            'ict_supervisor' => 'ICT Supervisor',
            'ict_support_staff' => 'ICT Support Staff',
        ];
    }
}
