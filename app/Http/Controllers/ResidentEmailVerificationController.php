<?php

namespace App\Http\Controllers;

use App\Actions\RecordPortalAccountEvent;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class ResidentEmailVerificationController extends Controller
{
    public const INVALID_LINK_MESSAGE = 'This confirmation link is invalid or has expired. Sign in and select Send a new confirmation link.';

    public const SEND_FAILED_MESSAGE = 'We could not send the confirmation email right now. Please try again in a few minutes. If it keeps failing, ask the Barangay Hall for help.';

    /**
     * Confirm a resident account's email from the signed link. The `signed` middleware rejects altered or
     * expired links; the hash must match the account's current email, so a link sent to an older address
     * cannot confirm a new one. Works without signing in, so the link can be opened on another device.
     */
    public function verify(Request $request, string $account, string $hash, RecordPortalAccountEvent $events): RedirectResponse
    {
        $account = User::query()->find((int) $account);
        if (! $account?->isResidentAccount() || ! hash_equals(sha1(strtolower($account->email)), $hash)) {
            return self::rejectedLink();
        }

        if ($account->email_verified_at === null) {
            $account->forceFill(['email_verified_at' => now()])->save();
            $events->handle('portal.email.verified', $account->resident_id, $account);
            if (auth('resident')->id() === $account->id) {
                auth('resident')->setUser($account);
            }
        }

        return redirect()->route(auth('resident')->check() ? 'portal.request.create' : 'portal.login')
            ->with('status', 'Your email address is confirmed. You can now request documents.');
    }

    /**
     * Where an altered, expired or mismatched confirmation link leads: one message for every case, so the
     * response says nothing about which accounts exist. Signed-in residents land on the page with the
     * Send a new confirmation link button. Rate-limited attempts use the same path with their own message.
     */
    public static function rejectedLink(?string $message = null): RedirectResponse
    {
        return redirect()->route(auth('resident')->check() ? 'portal.request.create' : 'portal.login')
            ->withErrors(['verification' => $message ?? __(self::INVALID_LINK_MESSAGE)]);
    }

    /**
     * Send a fresh confirmation link to the signed-in resident's own email address. A mail server that is down
     * or refuses the message, or a server that does not deliver email at all, is explained to the resident instead
     * of claiming the link was sent, and recorded in the audit log.
     */
    public function send(Request $request, RecordPortalAccountEvent $events): RedirectResponse
    {
        $account = $request->user();
        if ($account->email_verified_at === null) {
            try {
                $account->sendResidentEmailConfirmation();
            } catch (Throwable $exception) {
                $events->confirmationMailNotSent($exception, $account);

                return back()->withErrors(['verification' => __(self::SEND_FAILED_MESSAGE)]);
            }
        }

        return back()->with('status', 'We sent a new confirmation link to your email address.');
    }
}
