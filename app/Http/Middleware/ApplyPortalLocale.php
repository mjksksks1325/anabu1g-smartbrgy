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
        // The resident password reset pages live at Fortify's /forgot-password and /reset-password addresses; they are
        // the portal's when they carry from=portal (in the link, or posted by the portal forms).
        $isResidentPortal = $request->is('portal', 'portal/*')
            || ($request->is('forgot-password', 'reset-password', 'reset-password/*') && ($request->query('from') === 'portal' || $request->input('from') === 'portal'));
        $isPersonnelWorkspace = $request->is('staff', 'staff/*', 'admin', 'admin/*');
        $cookie = $isPersonnelWorkspace ? PortalLocalization::ADMIN_COOKIE : PortalLocalization::COOKIE;
        $preference = $request->cookie($cookie);

        app()->setLocale(($isResidentPortal || $isPersonnelWorkspace) && in_array($preference, PortalLocalization::LOCALES, true) ? $preference : 'en');

        return $next($request);
    }
}
