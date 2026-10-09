<?php

namespace App\Http\Controllers;

use App\PortalLocalization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Js;
use Illuminate\Validation\Rule;

class PortalLocaleController extends Controller
{
    public function catalog(): Response
    {
        return response('window.PORTAL_I18N.catalogs = '.Js::from(PortalLocalization::catalogs()).';', 200)
            ->header('Content-Type', 'application/javascript; charset=UTF-8')
            ->header('X-Content-Type-Options', 'nosniff');
    }

    public function __invoke(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate(['locale' => ['required', 'string', Rule::in(PortalLocalization::LOCALES)]]);
        $locale = $validated['locale'];
        app()->setLocale($locale);
        $cookie = Cookie::make(PortalLocalization::COOKIE, $locale, 60 * 24 * 365, '/', null, $request->isSecure(), true, false, 'lax');

        if ($request->expectsJson()) {
            return response()->json(['locale' => $locale])->withCookie($cookie);
        }

        $previous = url()->previous();
        $destination = str_starts_with($previous, url('/portal')) ? $previous : route('home');

        return redirect()->to($destination)->withCookie($cookie);
    }
}
