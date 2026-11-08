<?php

namespace App\Providers;

use App\Support\SuperadminNotificationGate;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Throwable;

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
        $this->configureUrlScheme();
        $this->configureNotificationPreferences();
        $this->assignLicensedDemoCoversOnce();

        Route::macro('filterableResource', function (string $name, string $controller) {
            Route::resource($name, $controller);
            Route::post("{$name}-filter", [$controller, 'updateFilters'])->name("{$name}.filter");
        });
    }

    /**
     * One-shot Railway helper: set ASSIGN_LICENSED_COVERS_ONCE=1 to copy verified
     * demo covers onto existing packages, then unset the variable after deploy.
     */
    protected function assignLicensedDemoCoversOnce(): void
    {
        if (env('ASSIGN_LICENSED_COVERS_ONCE') !== '1') {
            return;
        }

        if ($this->app->runningUnitTests()) {
            return;
        }

        $flag = storage_path('app/licensed-covers-assigned.flag');

        if (is_file($flag)) {
            return;
        }

        try {
            Artisan::call('demo:assign-licensed-covers', [
                '--operator' => (int) env('DEMO_TOUR_OPERATOR_ID', 1),
            ]);
            file_put_contents(
                $flag,
                now()->toIso8601String()."\n".Artisan::output()
            );
        } catch (Throwable) {
            // Leave flag unset so a later request can retry after a failed boot race.
        }
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

        Password::defaults(
            fn (): ?Password => app()->isProduction()
                ? Password::min(12)
                    ->mixedCase()
                    ->letters()
                    ->numbers()
                    ->symbols()
                    ->uncompromised()
                : null,
        );
    }

    /**
     * Let a Super Admin hide optional in-app alerts without blocking essential ones.
     */
    protected function configureNotificationPreferences(): void
    {
        Event::listen(function (NotificationSending $event) {
            if (! SuperadminNotificationGate::shouldDeliver($event->notifiable, $event->notification)) {
                return false;
            }
        });
    }

    protected function configureUrlScheme(): void
    {
        if (str_starts_with(config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
