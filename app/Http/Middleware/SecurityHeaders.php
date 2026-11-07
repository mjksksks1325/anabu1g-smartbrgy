<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser security headers on every response. The Content-Security-Policy loads scripts, styles, images and
 * connections from this site only, plus Google Fonts and the Google Maps embed the portal shows; it forbids
 * plugins, other sites framing the pages, and forms or <base> pointing elsewhere. Inline scripts and eval stay
 * allowed: the staff workspace uses inline event handlers and Livewire/Alpine evaluate expressions, so a
 * nonce-only policy would need those rewritten first. HSTS is sent only for HTTPS requests in production.
 * A header a response already carries (e.g. a stricter one from a controller) is left as it is.
 */
class SecurityHeaders
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'Content-Security-Policy' => $this->contentSecurityPolicy(),
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'same-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), serial=(), bluetooth=()',
        ];
        if ($request->isSecure() && app()->isProduction()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000';
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }

    private function contentSecurityPolicy(): string
    {
        // While `npm run dev` serves assets (public/hot), its origin and live-reload socket are allowed too.
        $vite = '';
        if (Vite::isRunningHot()) {
            $origin = rtrim((string) file_get_contents(public_path('hot')));
            $vite = ' '.$origin.' '.preg_replace('#^http#', 'ws', $origin);
        }

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'".$vite,
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com".$vite,
            "font-src 'self' data: https://fonts.gstatic.com",
            "img-src 'self' data: blob:",
            "connect-src 'self'".$vite,
            'frame-src https://maps.google.com https://www.google.com',
            "frame-ancestors 'self'",
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
        ]);
    }
}
