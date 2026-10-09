<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePersonnelTwoFactorAccount
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('two-factor.login', 'two-factor.login.store') && $request->session()->has('login.id')) {
            $challengedUserId = $request->session()->get('login.id');
            $user = is_int($challengedUserId) || is_string($challengedUserId) ? User::find($challengedUserId) : null;
            if (! $user?->canLoginAsPersonnel()) {
                $request->session()->forget(['login.id', 'login.remember', 'url.intended']);

                return redirect()->route('login');
            }
        }

        return $next($request);
    }
}
