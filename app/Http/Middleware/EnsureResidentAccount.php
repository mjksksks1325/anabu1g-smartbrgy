<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureResidentAccount
{
    public const ASSISTANCE = 'Your resident account cannot currently use online services. Please visit Barangay Anabu I-G for assistance with your account or resident record.';

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('resident')->user();
        if (! $user) {
            $loginQuery = match (true) {
                $request->routeIs('portal.request.create') => ['next' => 'request', 'service' => $request->string('service')->toString()],
                default => [],
            };

            return $request->expectsJson()
                ? response()->json(['message' => 'Sign in to your resident account to continue.'], 401)
                : redirect()->guest(route('portal.login', $loginQuery));
        }
        if (! $user->canUseResidentPortal()) {
            return $request->expectsJson()
                ? response()->json(['message' => self::ASSISTANCE], 403)
                : response()->view('portal.assistance', ['message' => self::ASSISTANCE], 403);
        }

        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
