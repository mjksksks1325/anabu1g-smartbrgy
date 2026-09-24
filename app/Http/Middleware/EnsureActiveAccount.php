<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guard = $request->is('portal*') ? 'resident' : 'web';
        $user = Auth::guard($guard)->user();

        if ($user && ! $user->is_active) {
            $loginRoute = $guard === 'resident' ? 'portal.login' : 'login';
            Auth::guard($guard)->logout();
            $request->session()->regenerate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your account is suspended. Contact the barangay administrator.'], 401);
            }

            return redirect()->route($loginRoute)->withErrors(['email' => 'Your account is suspended. Contact the barangay administrator.']);
        }

        return $next($request);
    }
}
