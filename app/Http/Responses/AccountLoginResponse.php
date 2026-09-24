<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse;
use Symfony\Component\HttpFoundation\Response;

class AccountLoginResponse implements LoginResponse
{
    public function toResponse(mixed $request): Response
    {
        return $request->wantsJson() ? response()->json(['two_factor' => false]) : redirect()->intended(route('dashboard', absolute: false));
    }
}
