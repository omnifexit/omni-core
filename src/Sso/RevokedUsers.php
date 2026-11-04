<?php

namespace Omnifexit\OmniCore\Sso;

use Illuminate\Support\Facades\Cache;

/**
 * Users whose sessions have to end: their roles changed, they were removed in OmniAuth, or they logged out. Only
 * sessions older than the flag end, so logging back in on one device doesn't save a session on another.
 */
class RevokedUsers
{
    /** The session key holding when this session logged in. */
    public const SESSION_KEY = 'omni_core_logged_in_at';

    /** Longer than a session lasts. */
    private const DAYS_KEPT = 30;

    public static function flag(int|string $userId): void
    {
        Cache::put(CacheKeys::revokedUser($userId), now()->getTimestamp(), now()->addDays(self::DAYS_KEPT));
    }

    /**
     * @param  int|null  $loggedInAt  When the session logged in. Without one, any flag counts.
     */
    public static function isFlagged(int|string $userId, ?int $loggedInAt = null): bool
    {
        $flaggedAt = Cache::get(CacheKeys::revokedUser($userId));

        if (! $flaggedAt) {
            return false;
        }

        return $loggedInAt === null || $loggedInAt < (int) $flaggedAt;
    }

    public static function clear(int|string $userId): void
    {
        Cache::forget(CacheKeys::revokedUser($userId));
    }
}
