<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;

/**
 * One answer to every password reset link request: sent, unknown email or throttled. A different answer
 * for an unknown address would tell anyone which emails have accounts. The broker's per-account throttle
 * and the 60-minute token expiry still apply; they are just not announced.
 */
class PasswordResetLinkRequestResponse implements FailedPasswordResetLinkRequestResponse, SuccessfulPasswordResetLinkRequestResponse
{
    public const MESSAGE = 'If an account uses this email address, we will send it a link to reset the password. The link works for 60 minutes. Check your Spam folder if it does not arrive.';

    public function __construct(public string $status = '') {}

    public function toResponse(mixed $request): JsonResponse|RedirectResponse
    {
        return $request->wantsJson()
            ? response()->json(['message' => __(self::MESSAGE)])
            : back()->with('status', __(self::MESSAGE));
    }
}
