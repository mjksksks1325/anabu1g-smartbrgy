<?php

namespace App\Http\Responses;

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

        return $request->wantsJson() ? response()->json(['two_factor' => false])
            : redirect()->route(StaffPermissions::landing($request->user()));
    }
}
