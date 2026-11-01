<?php

namespace Omnifexit\OmniCore;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Sentinel\Sentinel;
use Laravel\Socialite\Facades\Socialite;
use Omnifexit\OmniCore\Sentinel\TailscaleAwareSentinelDriver;
use Omnifexit\OmniCore\Sso\OmniAuthProvider;
use Omnifexit\OmniCore\Sso\RevokedUsers;

class OmniCoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/omni-core.php', 'omni-core');
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/omni-core.php' => config_path('omni-core.php')], 'omni-core-config');

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

        Event::listen(Login::class, function (Login $event): void {
            $id = $event->user->getAuthIdentifier();

            if ($id !== null) {
                RevokedUsers::clear($id);
            }
        });
    }
}
