<?php

namespace App\Providers;

use App\Models\User;
use App\Models\UserTenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\URL;

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
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
        $this->configureDefaults();

        Gate::define('tenant-dashboard-access', function (UserTenant $user) {
            $hasAccess = $user->isTenant === true;
        
            $latestReservation = $user->reservation()->latest()->first();
            $hasActiveReservation = $latestReservation?->tenant?->reservation_id !== null;
        
            return $hasAccess && $hasActiveReservation;
        });

        Gate::define('reservation-access', function (UserTenant $user) {
            $isTenant = $user->isTenant === true;
        
            $latestReservation = $user->reservation()->latest()->first();
            $hasActiveReservation = $latestReservation?->tenant?->reservation_id !== null;
        
            return !$hasActiveReservation;
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
