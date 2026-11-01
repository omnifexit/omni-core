<?php

namespace Omnifexit\OmniCore\Jobs\Auth;

/**
 * Asks OmniAuth to send a client app its current users (as a SyncUsersJob). Sent by the app's "sync users" button,
 * and by OmniAuth's own admin pages. Progress goes to statusKey().
 */
class RequestUserSyncJob extends ClientAppJob
{
    public int $backoff = 5;

    public int $tries = 3;

    public function __construct(
        public readonly string $clientId,
        public readonly string $dispatchId,
    ) {}

    public static function queue(?string $receiver = null): string
    {
        return 'omni-sync-request';
    }

    public static function sentByOmniAuth(): bool
    {
        return true;
    }

    public static function sentByClientApps(): bool
    {
        return true;
    }

    /**
     * The Redis hash OmniAuth writes this request's progress to.
     */
    public function statusKey(): string
    {
        return "sync:dispatch:{$this->dispatchId}";
    }
}
