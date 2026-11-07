<?php

namespace App\Http\Middleware;

use App\Actions\VerifySupervisorPassword;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class RequireSupervisorPassword
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            return response()->json(['message' => __('Sign in as staff to continue.')], 401);
        }

        $password = $request->isJson() ? $request->json('super_admin_password') : null;
        if (! is_string($password) || $password === '' || strlen($password) > 255) {
            return response()->json([
                'message' => __('The active Barangay Captain or Super Admin password is required to archive or delete.'),
                'errors' => ['super_admin_password' => [__('Enter the active Barangay Captain or Super Admin password.')]],
            ], 422);
        }

        $rateLimitKey = 'supervisor-record-approval:'.$actor->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            return response()->json([
                'message' => __('Too many incorrect passwords. Try again in a moment.'),
            ], 429)->header('Retry-After', (string) RateLimiter::availableIn($rateLimitKey));
        }

        if (! app(VerifySupervisorPassword::class)->passes($password)) {
            RateLimiter::hit($rateLimitKey, 60);

            return response()->json([
                'message' => __('The active Barangay Captain or Super Admin password is incorrect. No record was changed.'),
                'errors' => ['super_admin_password' => [__('The active Barangay Captain or Super Admin password is incorrect.')]],
            ], 422);
        }

        RateLimiter::clear($rateLimitKey);
        $request->request->remove('super_admin_password');
        if ($request->isJson()) {
            $request->json()->remove('super_admin_password');
        }

        return $next($request);
    }
}
