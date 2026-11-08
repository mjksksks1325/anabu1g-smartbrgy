<?php

namespace App\Actions\Auth;

use App\Actions\TrustedDeviceCookie;
use App\Models\User;
use Illuminate\Contracts\Auth\StatefulGuard;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\LoginRateLimiter;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfTrustedDeviceTwoFactor extends RedirectIfTwoFactorAuthenticatable
{
    public function __construct(
        StatefulGuard $guard,
        LoginRateLimiter $limiter,
        private readonly TrustedDeviceCookie $trustedDeviceCookie,
    ) {
        parent::__construct($guard, $limiter);
    }

    protected function twoFactorChallengeResponse(mixed $request, mixed $user): Response
    {
        if ($user instanceof User && $this->trustedDeviceCookie->isTrusted($request, $user)) {
            $this->guard->login($user, $request->boolean('remember'));
            $request->session()->regenerate();

            return app(LoginResponse::class)->toResponse($request);
        }

        return parent::twoFactorChallengeResponse($request, $user);
    }
}
