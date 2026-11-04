<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Document requests need a confirmed email address on the resident account. Runs after
 * EnsureResidentAccount, so the user is a signed-in resident who may use the portal.
 */
class EnsureResidentEmailVerified
{
    public const MESSAGE = 'Confirm your email address before requesting documents. Open the link we sent to your email, or send a new one.';

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->email_verified_at !== null) {
            return $next($request);
        }

        return $request->expectsJson()
            ? response()->json(['message' => __(self::MESSAGE)], 403)
            : response()->view('portal.verify-email', ['message' => __(self::MESSAGE)], 403);
    }
}
