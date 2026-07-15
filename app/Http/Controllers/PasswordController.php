<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class PasswordController extends Controller
{
    private function passwordRules(): array
    {
        return [
            'required',
            'confirmed',
            PasswordRule::min(8)
                ->mixedCase()
                ->numbers()
                ->symbols(),
        ];
    }

    public function createForgotPassword(): View
    {
        return view('auth.forgot-password');
    }

    public function storeForgotPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink($request->only('email'));

        if ($status !== Password::RESET_LINK_SENT) {
            return back()
                ->withErrors(['email' => __($status)])
                ->onlyInput('email');
        }

        return back()->with('status', 'We have emailed your password reset link.');
    }

    public function createResetPassword(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->string('email'),
        ]);
    }

    public function storeResetPassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => $this->passwordRules(),
        ]);

        $status = Password::reset(
            $validated,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                    'force_password_change' => false,
                    'password_changed_at' => now(),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withErrors(['email' => [__($status)]])
                ->onlyInput('email');
        }

        return redirect()
            ->route('login')
            ->with('status', 'Your password has been reset. You can sign in now.');
    }

    public function editCurrentPassword(Request $request): View
    {
        return view('auth.change-password', [
            'passwordExpired' => $request->user()?->passwordExpired() ?? false,
            'requiresPasswordChange' => $request->user()?->requiresPasswordChange() ?? false,
        ]);
    }

    public function updateCurrentPassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => [
                'different:current_password',
                ...$this->passwordRules(),
            ],
        ]);

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'force_password_change' => false,
            'password_changed_at' => now(),
        ])->save();

        return redirect()
            ->route('dashboard')
            ->with('status', 'Password updated successfully.');
    }
}
