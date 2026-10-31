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
            return response()->json(['message' => 'Kailangan mag-login bilang staff bago magpatuloy.'], 401);
        }

        $password = $request->isJson() ? $request->json('super_admin_password') : null;
        if (! is_string($password) || $password === '' || strlen($password) > 255) {
            return response()->json([
                'message' => 'Kailangan ang password ng active Barangay Captain o Super Admin para mag-archive o mag-delete.',
                'errors' => ['super_admin_password' => ['Ilagay ang password ng active Barangay Captain o Super Admin.']],
            ], 422);
        }

        $rateLimitKey = 'supervisor-record-approval:'.$actor->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            return response()->json([
                'message' => 'Masyadong maraming maling password. Subukan ulit pagkalipas ng ilang sandali.',
            ], 429)->header('Retry-After', (string) RateLimiter::availableIn($rateLimitKey));
        }

        if (! app(VerifySupervisorPassword::class)->passes($password)) {
            RateLimiter::hit($rateLimitKey, 60);

            return response()->json([
                'message' => 'Hindi tama ang password ng active Barangay Captain o Super Admin. Walang binagong record.',
                'errors' => ['super_admin_password' => ['Hindi tama ang password ng active Barangay Captain o Super Admin.']],
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
