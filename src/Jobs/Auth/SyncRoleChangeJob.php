<?php

namespace Omnifexit\OmniCore\Jobs\Auth;

/**
 * A role of a client app was created, changed or deleted in OmniAuth; the app mirrors it with these permissions.
 */
class SyncRoleChangeJob extends ClientAppJob
{
    public int $backoff = 5;

    public int $tries = 3;

    /**
     * @param  list<string>  $permissions
     */
    public function __construct(
        public readonly ?string $clientId,
        public readonly array $permissions,
        public readonly string $roleName,
        public readonly bool $isDeleted = false,
    ) {}

    /**
     * Each app's own sync queue ($receiver is the app, e.g. omnidesk).
     */
    public static function queue(?string $receiver = null): string
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
