<?php

namespace App\Http\Middleware;

use App\StaffPermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffAccess
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user?->canLoginAsPersonnel(), 403);
        if (! $user->isSuperAdmin()) {
            $name = $request->route()->getName();
            if ($name === 'staff.dashboard' && ! $request->expectsJson() && ! $user->hasPermission('dashboard.view')) {
                return redirect()->route(StaffPermissions::landing($user));
            }
            abort_unless($user->hasAnyPermission(StaffPermissions::forRoute($name)), 403);
        }

        return $next($request);
    }
}
