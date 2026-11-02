<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Crypt;
use JsonException;

class TrustedDeviceCookie
{
    public const COOKIE_NAME = 'staff_2fa_trusted_device';

    private const TRUSTED_DAYS = 30;

    public function issue(User $user, Request $request): \Symfony\Component\HttpFoundation\Cookie
    {
        $expiresAt = now()->addDays(self::TRUSTED_DAYS);
        $payload = [
            'user_id' => (string) $user->getAuthIdentifier(),
            'secret_fingerprint' => hash('sha256', (string) $user->two_factor_secret),
            'password_fingerprint' => hash('sha256', $user->getAuthPassword()),
            'confirmed_at' => $user->two_factor_confirmed_at?->getTimestamp(),
            'expires_at' => $expiresAt->getTimestamp(),
        ];

        $value = Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));

        return Cookie::make(
            self::COOKIE_NAME,
            $value,
            self::TRUSTED_DAYS * 24 * 60,
            '/',
            null,
            $request->isSecure() || app()->isProduction(),
            true,
            false,
            'strict',
        );
    }

    public function isTrusted(Request $request, User $user): bool
    {
        if (! $user->hasEnabledTwoFactorAuthentication()) {
            return false;
        }

        $value = $request->cookie(self::COOKIE_NAME);

        if (! is_string($value) || $value === '') {
            return false;
        }

        try {
            $payload = json_decode(Crypt::decryptString($value), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            return false;
        }

        if (! is_array($payload)) {
            return false;
        }

        return ($payload['user_id'] ?? null) === (string) $user->getAuthIdentifier()
            && is_string($payload['secret_fingerprint'] ?? null)
            && hash_equals($payload['secret_fingerprint'], hash('sha256', (string) $user->two_factor_secret))
            && is_string($payload['password_fingerprint'] ?? null)
            && hash_equals($payload['password_fingerprint'], hash('sha256', $user->getAuthPassword()))
            && ($payload['confirmed_at'] ?? null) === $user->two_factor_confirmed_at?->getTimestamp()
            && is_int($payload['expires_at'] ?? null)
            && $payload['expires_at'] > now()->getTimestamp();
    }
}
