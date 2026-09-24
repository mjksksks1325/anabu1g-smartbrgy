<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureGuestResidentPortal
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('resident')->user();
        if (! $user) {
            return $next($request);
        }

        if (! $request->isMethodSafe()) {
            abort(403, 'Log out before creating a resident account.');
        }

        if ($user->isResidentAccount()) {
            return redirect()->route($request->routeIs('portal.login') ? 'home' : 'portal.account');
        }

        return redirect()->route($request->routeIs('portal.login') ? 'home' : 'portal.account');
    }
}
