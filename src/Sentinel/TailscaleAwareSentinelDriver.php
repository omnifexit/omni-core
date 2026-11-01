<?php

namespace Omnifexit\OmniCore\Sentinel;

use Laravel\Sentinel\Drivers\Laravel as LaravelSentinelDriver;
use Symfony\Component\HttpFoundation\IpUtils;

class TailscaleAwareSentinelDriver extends LaravelSentinelDriver
{
    /**
     * Also trust Tailscale's CGNAT range (100.64.0.0/10), which Symfony's
     * IpUtils doesn't classify as private, so Sentinel doesn't 401 requests
     * proxied in over the team's Tailscale network.
     */
    protected function isPrivateIp(string $requestIp): bool
    {
        return parent::isPrivateIp($requestIp) || IpUtils::checkIp($requestIp, '100.64.0.0/10');
    }
}
