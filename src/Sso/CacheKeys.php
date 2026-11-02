<?php

namespace Omnifexit\OmniCore\Sso;

use Illuminate\Support\Str;

/**
 * Builds this app's SSO cache keys. Apps share one Redis keyspace, so each key starts with the app's prefix.
 */
class CacheKeys
{
    public static function prefix(): string
    {
        $prefix = config('omni-core.sso.cache_prefix');

        if (is_string($prefix) && $prefix !== '') {
            return $prefix;
        }

        return Str::after((string) config('omni-core.service'), 'omni');
    }

    public static function revokedUser(int|string $userId): string
    {
        return self::prefix().":revoke_user:{$userId}";
    }

    public static function launcher(int|string $omniAuthId): string
    {
        return self::prefix().":launcher:{$omniAuthId}";
    }
}
