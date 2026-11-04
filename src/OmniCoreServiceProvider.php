<?php

namespace Omnifexit\OmniCore;

use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Sentinel\Sentinel;
use Laravel\Socialite\Facades\Socialite;
use Omnifexit\OmniCore\Alerts\DiscordAlerts;
use Omnifexit\OmniCore\Console\SyncPermissionsCommand;
use Omnifexit\OmniCore\Sentinel\TailscaleAwareSentinelDriver;
use Omnifexit\OmniCore\Sso\OmniAuthProvider;
use Omnifexit\OmniCore\Sso\RevokedUsers;
use Throwable;

class OmniCoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/omni-core.php', 'omni-core');

        $this->app->singleton(DiscordAlerts::class);
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/omni-core.php' => config_path('omni-core.php')], 'omni-core-config');

        if ($this->app->runningInConsole()) {
            $this->commands([SyncPermissionsCommand::class]);
        }

        // Laravel's Sentinel (pulled in by Horizon) 401s requests proxied in over Tailscale, because
        // it treats the 100.64.0.0/10 CGNAT range as public internet. Only registered when Sentinel
        // is installed, so services without Horizon can use this package too.
        if (class_exists(Sentinel::class)) {
            Sentinel::extend('laravel', fn ($container) => new TailscaleAwareSentinelDriver(fn () => $container));
        }

        if (class_exists(Socialite::class)) {
            Socialite::extend('omniauth', function ($app) {
                $config = $app['config']['services.omniauth'] ?? [];

                return new OmniAuthProvider(
                    $app['request'],
                    $config['client_id'] ?? null,
                    $config['client_secret'] ?? null,
                    $config['redirect'] ?? null,
                );
            });
        }

        $this->registerAlerts();

        Event::listen(Login::class, function (): void {
            if (app()->bound('session')) {
                session()->put(RevokedUsers::SESSION_KEY, now()->getTimestamp());
            }
        });
    }

    private function registerAlerts(): void
    {
        Event::listen(JobExceptionOccurred::class, fn (JobExceptionOccurred $event) => $this->app->make(DiscordAlerts::class)->jobAttemptFailed($event));
        Event::listen(JobFailed::class, fn (JobFailed $event) => $this->app->make(DiscordAlerts::class)->jobFailed($event));

        $handler = $this->app->make(ExceptionHandler::class);

        if (method_exists($handler, 'reportable')) {
            // Returns nothing on purpose: returning false would stop Laravel from logging the exception.
            $handler->reportable(function (Throwable $exception): void {
                $this->app->make(DiscordAlerts::class)->exceptionReported($exception);
            });
        }
    }
}
