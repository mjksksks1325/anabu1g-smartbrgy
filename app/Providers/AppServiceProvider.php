<?php

namespace App\Providers;

use App\Models\CabinetDevice;
use App\Models\User;
use App\StaffPermissions;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * `@scopeErrors($bag)` sets the validation errors that the following form controls see, until the next `@scopeErrors`.
     * Pages with several forms sharing field names (one per record) give each form only its own errors.
     * Flux reads both the template's `$errors` and the shared bag, so both are set.
     */
    protected function registerBladeDirectives(): void
    {
        Blade::directive('scopeErrors', fn (string $expression): string => "<?php \$errors = {$expression}; \$__env->share('errors', \$errors); ?>");
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerBladeDirectives();
        RateLimiter::for('cabinet-device', function (Request $request): Limit {
            $cabinet = $request->route('cabinet');

            return Limit::perMinute(300)->by($cabinet instanceof CabinetDevice ? $cabinet->identifier : (string) $cabinet);
        });

        Gate::define('access-personnel', fn (User $user): bool => $user->is_active && $user->resident_id === null && in_array($user->role, ['admin', 'captain', 'staff', 'viewer'], true));
        Gate::define('operate-personnel', fn (User $user): bool => $user->canLoginAsPersonnel());
        Gate::define('access-employee-settings', fn (User $user): bool => $user->is_active && $user->resident_id === null && in_array($user->role, ['admin', 'captain', 'staff', 'viewer'], true));
        foreach (StaffPermissions::keys() as $permission) {
            Gate::define($permission, fn (User $user): bool => $user->hasPermission($permission));
        }
        Gate::define('view-administration', fn (User $user): bool => $user->hasAdministrativeAccess());
        Gate::define('view-rfid-files', fn (User $user): bool => $user->canTrackRfidFiles());

        Event::listen(Login::class, function (Login $event): void {
            if (! $event->user instanceof User || ! $event->user->is_active) {
                return;
            }

            DB::table('administrative_audits')->insert([
                'user_id' => $event->user->id,
                'actor' => $event->user->name,
                'action' => 'auth.login',
                'type' => 'auth',
                'record' => $event->user->isResidentAccount() ? 'resident_portal' : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        // The owner finished a reset through the emailed link. Recorded without the token or the new password.
        Event::listen(PasswordReset::class, function (PasswordReset $event): void {
            if (! $event->user instanceof User) {
                return;
            }

            DB::table('administrative_audits')->insert([
                'user_id' => $event->user->id,
                'actor' => $event->user->name,
                'action' => 'auth.password-reset-completed',
                'type' => 'security',
                'record' => $event->user->isResidentAccount() ? 'resident_portal' : (string) $event->user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // One staff password policy everywhere (creation by an administrator, the first Super Admin command,
        // a change in Settings and a reset): 12+ characters, upper and lower case, a number and a symbol (any
        // punctuation, so a period counts). The breach check calls an external service, so it runs in production only.
        // Existing password hashes are not affected; the policy applies the next time a password is set.
        Password::defaults(function (): Password {
            $rule = Password::min(12)->mixedCase()->letters()->numbers()->symbols();

            return app()->isProduction() ? $rule->uncompromised() : $rule;
        });
    }
}
