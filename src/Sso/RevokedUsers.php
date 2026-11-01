<?php

namespace Omnifexit\OmniCore\Sso;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Flags local users whose roles changed or who were removed in OmniAuth, so EnsureUserNotRevoked logs them out on
 * their next request. Cleared when the user logs in again.
 */
class RevokedUsers
{
    public static function cachePrefix(): string
    {
        $prefix = config('omni-core.sso.cache_prefix');

        if (is_string($prefix) && $prefix !== '') {
            return $prefix;
        }

        return Str::after((string) config('omni-core.service'), 'omni');
    }

    public static function key(int|string $userId): string
    {
        return self::cachePrefix().":revoke_user:{$userId}";
    }

    public static function flag(int|string $userId): void
    {
        Cache::put(self::key($userId), true, now()->addDay());
    }

    public static function isFlagged(int|string $userId): bool
    {
        return (bool) Cache::get(self::key($userId));
    }

    public static function clear(int|string $userId): void
    {
        Cache::forget(self::key($userId));
    }
}
