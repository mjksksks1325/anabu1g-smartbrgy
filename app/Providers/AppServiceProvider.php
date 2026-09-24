<?php

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
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
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Gate::define('access-employee-settings', fn (User $user): bool => ! $user->isResidentAccount());
        Gate::define('view-administration', fn (User $user): bool => $user->isSuperAdmin());
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

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
