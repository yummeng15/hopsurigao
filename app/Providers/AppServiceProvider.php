<?php

namespace App\Providers;

use App\Support\SuperadminNotificationGate;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
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
        $this->configureUrlScheme();
        $this->configureNotificationPreferences();

        Route::macro('filterableResource', function (string $name, string $controller) {
            Route::resource($name, $controller);
            Route::post("{$name}-filter", [$controller, 'updateFilters'])->name("{$name}.filter");
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
