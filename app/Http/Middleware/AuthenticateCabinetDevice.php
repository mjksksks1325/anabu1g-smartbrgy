<?php

namespace App\Http\Middleware;

use App\Models\CabinetDevice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateCabinetDevice
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $cabinet = $request->route('cabinet');
        $token = $request->header('X-Device-Token');

        if (! $cabinet instanceof CabinetDevice || ! is_string($token) || $token === ''
            || $cabinet->api_token_hash === null
            || ! hash_equals($cabinet->api_token_hash, hash('sha256', $token))) {
            abort(401, 'Invalid device token.');
        }

        return $next($request);
    }
}
