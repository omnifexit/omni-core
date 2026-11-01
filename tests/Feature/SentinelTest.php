<?php

use Laravel\Sentinel\Sentinel;
use Omnifexit\OmniCore\Sentinel\TailscaleAwareSentinelDriver;

it('registers the Tailscale-aware driver as Sentinel\'s laravel driver', function () {
    expect(Sentinel::driver('laravel'))->toBeInstanceOf(TailscaleAwareSentinelDriver::class);
});

it('treats the Tailscale CGNAT range as private and other public IPs as public', function (string $ip, bool $private) {
    $driver = new class(fn () => app()) extends TailscaleAwareSentinelDriver
    {
        public function check(string $ip): bool
        {
            return $this->isPrivateIp($ip);
        }
    };

    expect($driver->check($ip))->toBe($private);
})->with([
    'tailscale' => ['100.100.12.34', true],
    'docker' => ['172.18.0.5', true],
    'public' => ['8.8.8.8', false],
]);
