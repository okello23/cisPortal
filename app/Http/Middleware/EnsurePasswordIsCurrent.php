<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsurePasswordIsCurrent
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): mixed  $next
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        if ($user === null || ! $user->requiresPasswordChange()) {
            return $next($request);
        }

        return redirect()
            ->route('password.change.edit')
            ->with('warning', $user->passwordExpired()
                ? 'Your password has expired. Please create a new one to continue.'
                : 'Please change the temporary password that was sent to your email before continuing.');
    }
}
