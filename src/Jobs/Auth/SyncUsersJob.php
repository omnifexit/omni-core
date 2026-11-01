<?php

namespace Omnifexit\OmniCore\Jobs\Auth;

/**
 * Both directions of a user sync. Without $usersData it's a request from a client app to OmniAuth; OmniAuth answers
 * with the same job carrying the client's users (UserData arrays with that client's roles).
 */
class SyncUsersJob extends ClientAppJob
{
    public int $backoff = 5;

    public int $tries = 3;

    /**
     * @param  list<array<string, mixed>>|null  $usersData
     */
    public function __construct(
        public readonly ?string $clientId = null,
        public readonly ?array $usersData = null,
        public readonly ?int $triggeringUserId = null,
    ) {}

    /**
     * A request goes to OmniAuth's shared request queue; the answer to the app's own response queue.
     */
    public static function queue(?string $receiver = null): string
    {
        return $receiver === null || $receiver === 'omniauth' ? 'omni-sync-request' : "{$receiver}-sync-response";
    }

    public static function sentByOmniAuth(): bool
    {
        return true;
    }

    public static function sentByClientApps(): bool
    {
        return true;
    }

    public function isRequest(): bool
    {
        return $this->usersData === null;
    }
}
