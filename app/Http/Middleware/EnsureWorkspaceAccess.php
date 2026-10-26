<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspaceAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('admin.tickets.*', 'dashboard.support-performance.export')) {
            abort_unless($request->user()->hasGlobalRole(), 403);
        }

        return $next($request);
    }
}
