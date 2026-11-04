<?php

namespace Omnifexit\OmniCore\Jobs\Auth;

/**
 * A user logged out of the suite. The app ends their sessions, on every device, on their next request.
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
     * Each app's own sync queue ($receiver is the app, e.g. omnidesk).
     */
    public static function queueName(?string $receiver = null): string
    {
        return "{$receiver}-sync-queue";
    }

    public static function sentByOmniAuth(): bool
    {
        return true;
    }

    public static function sentByClientApps(): bool
    {
        return false;
    }
}
