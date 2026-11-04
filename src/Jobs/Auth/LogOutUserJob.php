<?php

namespace Omnifexit\OmniCore\Jobs\Auth;

/**
 * Both directions of logging out. A client app tells OmniAuth a user logged out, because the user's OmniAuth session
 * has often expired by then. OmniAuth sends the same job to every app they can reach, which ends their sessions, on
 * every device, on their next request.
 */
class LogOutUserJob extends ClientAppJob
{
    public int $backoff = 5;

    public int $tries = 3;

    public function __construct(
        public readonly ?string $clientId,
        public readonly int $omniAuthUserId,
    ) {}

    /**
     * To OmniAuth's shared request queue, or to each app's own sync queue ($receiver is the app, e.g. omnidesk).
     */
    public static function queueName(?string $receiver = null): string
    {
        return $receiver === null || $receiver === 'omniauth' ? 'omni-sync-request' : "{$receiver}-sync-queue";
    }

    public static function sentByOmniAuth(): bool
    {
        return true;
    }

    public static function sentByClientApps(): bool
    {
        return true;
    }
}
