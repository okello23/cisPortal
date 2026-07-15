<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt([...$credentials, 'active' => true], $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'The provided credentials are invalid.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        if ($request->user()?->requiresPasswordChange()) {
            return redirect()
                ->route('password.change.edit')
                ->with('warning', $request->user()->passwordExpired()
                    ? 'Your password has expired. Please create a new one to continue.'
                    : 'Please change the temporary password that was sent to your email before continuing.');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
