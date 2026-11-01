<?php

namespace Omnifexit\OmniCore;

use Illuminate\Support\ServiceProvider;
use Laravel\Sentinel\Sentinel;
use Omnifexit\OmniCore\Sentinel\TailscaleAwareSentinelDriver;

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
    }
}
