<?php

namespace App\Http\Responses;

use App\Http\Middleware\RequirePrivilegedTwoFactor;
use App\Models\User;
use Laravel\Fortify\Http\Responses\PasswordConfirmedResponse;
use Symfony\Component\HttpFoundation\Response;

class EnrollmentPasswordConfirmedResponse extends PasswordConfirmedResponse
{
    public function toResponse(mixed $request): Response
    {
        $user = $request->user('web');
        if (config('access.privileged_two_factor') && $user instanceof User && $user->requiresTwoFactorAuthentication() && ! $user->hasConfirmedTwoFactor()) {
            $request->session()->put(RequirePrivilegedTwoFactor::PASSWORD_CONFIRMATION_KEY, ['user_id' => $user->id, 'confirmed_at' => now()->getTimestamp()]);
            if (! $request->wantsJson()) {
                $request->session()->forget('url.intended');

                return redirect()->route('security.edit');
            }
        }

        return parent::toResponse($request);
    }
}
