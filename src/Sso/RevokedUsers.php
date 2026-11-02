<?php

namespace Omnifexit\OmniCore\Sso;

use Illuminate\Support\Facades\Cache;

/**
 * Flags local users whose roles changed or who were removed in OmniAuth, so EnsureUserNotRevoked logs them out on
 * their next request. Cleared when the user logs in again.
 */
class RevokedUsers
{
    public static function flag(int|string $userId): void
    {
        Cache::put(CacheKeys::revokedUser($userId), true, now()->addDay());
    }

    public static function isFlagged(int|string $userId): bool
    {
        return (bool) Cache::get(CacheKeys::revokedUser($userId));
    }

    public static function clear(int|string $userId): void
    {
        Cache::forget(CacheKeys::revokedUser($userId));
    }
}
