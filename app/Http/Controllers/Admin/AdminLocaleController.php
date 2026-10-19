<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\PortalLocalization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Js;
use Illuminate\Validation\Rule;

class AdminLocaleController extends Controller
{
    public function catalog(): Response
    {
        return response('window.ADMIN_I18N.catalogs = '.Js::from(PortalLocalization::catalogs()).';', 200)
            ->header('Content-Type', 'application/javascript; charset=UTF-8')
            ->header('X-Content-Type-Options', 'nosniff');
    }

    public function __invoke(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(PortalLocalization::LOCALES)],
        ]);
        $locale = $validated['locale'];
        app()->setLocale($locale);

        $cookie = Cookie::make(PortalLocalization::ADMIN_COOKIE, $locale, 60 * 24 * 365, '/', null, $request->isSecure(), true, false, 'lax');

        if ($request->expectsJson()) {
            return response()->json(['locale' => $locale])->withCookie($cookie);
        }

        $previous = $request->headers->get('referer', '');
        $previousUrl = parse_url($previous);
        $previousHost = is_array($previousUrl) ? ($previousUrl['host'] ?? null) : null;
        $previousPath = is_array($previousUrl) ? ($previousUrl['path'] ?? '') : '';
        $isWorkspacePath = str_starts_with($previousPath, '/staff/') || str_starts_with($previousPath, '/admin/') || in_array($previousPath, ['/staff', '/admin'], true);
        $isLocalWorkspaceUrl = $previousHost === $request->getHost() && $isWorkspacePath;

        return redirect()->to($isLocalWorkspaceUrl ? $previous : route('staff.dashboard'))->withCookie($cookie);
    }
}
