<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * A privileged personnel account (User::requiresTwoFactorAuthentication) that has not confirmed two-factor
 * authentication reaches only what it needs to set it up: the security settings page, password confirmation,
 * the two-factor setup endpoints, the settings Livewire components, language and assets, and logging out.
 * Everything else, pages, JSON APIs and other Livewire requests, sends it to set up two-factor authentication.
 * The rule is read from the account's current assignments on every request, so a new privileged permission
 * applies at once. Once set up, two-factor authentication cannot be switched off for such an account.
 */
class RequirePrivilegedTwoFactor
{
    public const MESSAGE = 'Your account has privileged access. Set up two-factor authentication to continue.';

    /** @var list<string> */
    private const ENROLLMENT_ROUTES = [
        'security.edit', 'password.confirm', 'password.confirm.store', 'staff.password.confirm', 'password.confirmation',
        'two-factor.enable', 'two-factor.confirm', 'two-factor.qr-code', 'two-factor.secret-key', 'two-factor.recovery-codes',
        'logout', 'legacy.logout', 'staff.locale', 'staff.translations', 'legacy.staff.locale', 'legacy.staff.translations',
    ];

    /** @var list<string> */
    private const ENROLLMENT_COMPONENTS = ['settings.security', 'settings.two-factor.recovery-codes'];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('web');

        if (! config('access.privileged_two_factor') || ! $user instanceof User || ! $user->requiresTwoFactorAuthentication()) {
            return $next($request);
        }

        if ($user->hasConfirmedTwoFactor()) {
            abort_if($request->routeIs('two-factor.disable'), 403, 'Two-factor authentication is required for this account and cannot be turned off.');

            return $next($request);
        }

        if ($request->routeIs(...self::ENROLLMENT_ROUTES) || $this->isEnrollmentComponentUpdate($request) || $this->isAsset($request)) {
            return $next($request);
        }

        if ($request->expectsJson() || $this->isLivewireUpdate($request)) {
            return response()->json(['message' => __(self::MESSAGE), 'two_factor_required' => true, 'setup_url' => route('security.edit')], 403);
        }

        return redirect()->route('security.edit')->with('two_factor_required', true);
    }

    private function isLivewireUpdate(Request $request): bool
    {
        return $request->isMethod('POST') && Str::endsWith((string) $request->route()?->getName(), 'livewire.update');
    }

    /** A Livewire update whose every component is one used to set up two-factor authentication. */
    private function isEnrollmentComponentUpdate(Request $request): bool
    {
        if (! $this->isLivewireUpdate($request)) {
            return false;
        }

        $components = $request->input('components');
        if (! is_array($components) || $components === []) {
            return false;
        }

        foreach ($components as $component) {
            $snapshot = is_array($component) && is_string($component['snapshot'] ?? null) ? json_decode($component['snapshot'], true) : null;
            if (! in_array($snapshot['memo']['name'] ?? null, self::ENROLLMENT_COMPONENTS, true)) {
                return false;
            }
        }

        return true;
    }

    /** Script and style files the setup page loads (Livewire and Flux). */
    private function isAsset(Request $request): bool
    {
        return $request->isMethod('GET') && (Str::startsWith($request->path(), ['livewire-', 'flux/']));
    }
}
