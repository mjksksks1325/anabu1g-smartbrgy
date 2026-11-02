<?php

namespace App\Http\Responses;

use App\Actions\TrustedDeviceCookie;
use App\Models\User;
use App\StaffPermissions;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Symfony\Component\HttpFoundation\Response;

class AccountLoginResponse implements LoginResponse, TwoFactorLoginResponse
{
    public function toResponse(mixed $request): Response
    {
        $request->session()->forget('url.intended');
        $request->session()->flash('personnel_login_greeting', true);

        $response = $request->wantsJson() ? response()->json(['two_factor' => false])
            : redirect()->route(StaffPermissions::landing($request->user()));

        if ($request->routeIs('two-factor.login.store') && $request->boolean('trust_device') && $request->user() instanceof User) {
            $response->headers->setCookie(app(TrustedDeviceCookie::class)->issue($request->user(), $request));
        }

        return $response;
    }
}
