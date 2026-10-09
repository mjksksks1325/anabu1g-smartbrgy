<?php

namespace App\Http\Middleware;

use App\PortalLocalization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyPortalLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $preference = $request->cookie(PortalLocalization::COOKIE);
        app()->setLocale($request->is('portal', 'portal/*') && in_array($preference, PortalLocalization::LOCALES, true) ? $preference : 'en');

        return $next($request);
    }
}
