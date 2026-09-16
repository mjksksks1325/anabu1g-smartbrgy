<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LogoutResponse;
use Symfony\Component\HttpFoundation\Response;

class PortalLogoutResponse implements LogoutResponse
{
    public function toResponse(mixed $request): Response
    {
        return $request->wantsJson()
            ? response()->noContent()
            : redirect()->route('home');
    }
}
