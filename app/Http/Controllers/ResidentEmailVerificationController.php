<?php

namespace App\Http\Controllers;

use App\Actions\RecordPortalAccountEvent;
use App\Models\User;
use App\Notifications\ResidentEmailVerificationNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ResidentEmailVerificationController extends Controller
{
    /**
     * Confirm a resident account's email from the signed link. The `signed` middleware rejects altered or
     * expired links; the hash must match the account's current email, so a link sent to an older address
     * cannot confirm a new one. Works without signing in, so the link can be opened on another device.
     */
    public function verify(Request $request, string $account, string $hash, RecordPortalAccountEvent $events): RedirectResponse
    {
        $account = User::query()->find((int) $account);
        abort_unless($account?->isResidentAccount() && hash_equals(sha1(strtolower($account->email)), $hash), 403);

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

    /** Send a fresh confirmation link to the signed-in resident's own email address. */
    public function send(Request $request): RedirectResponse
    {
        $account = $request->user();
        if ($account->email_verified_at === null) {
            $account->notify((new ResidentEmailVerificationNotification)->locale(app()->getLocale()));
        }

        return back()->with('status', 'We sent a new confirmation link to your email address.');
    }
}
