<?php

namespace App\Providers;

use App\Models\Payout;
use App\Models\User;
use App\Models\UserTenant;
use App\Support\TenantAccess;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\URL;
use App\Models\Reservation;
use App\Policies\ReservationPolicy;

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

        Gate::policy(Reservation::class, ReservationPolicy::class);

        Gate::define('tenant-dashboard-access', function (UserTenant $user) {
            return TenantAccess::hasActiveTenant($user);
        });
         
        Gate::define('tenant-payout-access', function (UserTenant $user) {
            return TenantAccess::hasUnsettledPayout($user);
        });
         
        Gate::define('reservation-access', function (UserTenant $user) {
            // "Kalau ingin mengajukan reservasi, payout harus selesai dulu" -- syarat ini
            // otomatis terpenuhi karena hasUnsettledPayout() jadi false setelah payout
            // berhasil dicairkan ATAU memang tidak ada saldo tersisa sama sekali
            return ! TenantAccess::hasActiveTenant($user) && ! TenantAccess::hasUnsettledPayout($user);
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
