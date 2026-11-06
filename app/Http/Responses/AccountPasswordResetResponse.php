<?php

namespace App\Http\Responses;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasswordResetResponse;
use Laravel\Fortify\Fortify;

/**
 * After a successful reset, residents return to the Resident Portal log in and staff to the staff log in.
 * The account is looked up only after the token was accepted, so this reveals nothing to the requester.
 */
class AccountPasswordResetResponse implements PasswordResetResponse
{
    public function __construct(public string $status) {}

    public function toResponse(mixed $request): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => trans($this->status)]);
        }

        $account = User::query()->whereRaw('LOWER(email) = ?', [Str::lower(trim((string) $request->input(Fortify::email())))])->first();

        return $account?->isResidentAccount()
            ? redirect()->route('portal.login')->with('status', 'Your password has been reset. Log in with your new password.')
            : redirect()->route('login')->with('status', trans($this->status));
    }
}
