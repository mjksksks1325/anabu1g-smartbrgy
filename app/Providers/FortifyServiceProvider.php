<?php

namespace App\Providers;

use App\Actions\Auth\RedirectIfTrustedDeviceTwoFactor;
use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\AccountLoginResponse;
use App\Http\Responses\AccountPasswordResetResponse;
use App\Http\Responses\PasswordResetLinkRequestResponse;
use App\Http\Responses\PortalLogoutResponse;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\LogoutResponse;
use Laravel\Fortify\Contracts\PasswordResetResponse;
use Laravel\Fortify\Contracts\RedirectsIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LogoutResponse::class, PortalLogoutResponse::class);
        $this->app->singleton(LoginResponse::class, AccountLoginResponse::class);
        $this->app->singleton(TwoFactorLoginResponse::class, AccountLoginResponse::class);
        $this->app->scoped(RedirectsIfTwoFactorAuthenticatable::class, RedirectIfTrustedDeviceTwoFactor::class);
        $this->app->bind(SuccessfulPasswordResetLinkRequestResponse::class, PasswordResetLinkRequestResponse::class);
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, PasswordResetLinkRequestResponse::class);
        $this->app->bind(PasswordResetResponse::class, AccountPasswordResetResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();

        // Resident reset links open the Resident Portal reset page; staff links keep the staff screen.
        ResetPassword::createUrlUsing(fn (User $user, #[\SensitiveParameter] string $token): string => route('password.reset', [
            'token' => $token,
            'email' => $user->getEmailForPasswordReset(),
            ...($user->isResidentAccount() ? ['from' => 'portal'] : []),
        ]));

        Fortify::authenticateUsing(function (Request $request): ?User {
            $user = User::query()->whereRaw('LOWER(email) = ?', [Str::lower(trim((string) $request->input('email')))])->first();

            if (! $user?->canLoginAsPersonnel()) {
                return null;
            }

            return Hash::check($request->input('password'), $user->password)
                ? $user
                : null;
        });
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn () => view('livewire.auth.login'));
        Fortify::verifyEmailView(fn () => view('livewire.auth.verify-email'));
        Fortify::twoFactorChallengeView(fn () => view('livewire.auth.two-factor-challenge'));
        Fortify::confirmPasswordView(fn () => view('livewire.auth.confirm-password'));
        Fortify::registerView(fn () => view('livewire.auth.register'));
        Fortify::resetPasswordView(fn (Request $request) => view($request->query('from') === 'portal' ? 'portal.reset-password' : 'livewire.auth.reset-password'));
        Fortify::requestPasswordResetLinkView(fn (Request $request) => view($request->query('from') === 'portal' ? 'portal.forgot-password' : 'livewire.auth.forgot-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('resident-registration', function (Request $request): array {
            return [
                Limit::perMinute(5)->by('resident-registration:'.$request->path().'|'.$request->ip()),
                Limit::perHour(30)->by('resident-registration-hour:'.$request->ip()),
            ];
        });
        RateLimiter::for('resident-login', function (Request $request): Limit {
            $key = Str::transliterate(Str::lower((string) $request->input('email')).'|'.$request->ip());

            return Limit::perMinute(5)->by('resident-login:'.$key);
        });
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

    }
}
