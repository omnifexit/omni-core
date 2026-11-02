<?php

namespace Omnifexit\OmniCore\Jobs\Auth;

/**
 * A client app's full permission list, sent once per deploy by omni-core:sync-permissions. OmniAuth makes its list for
 * that client match it.
 */
class SyncPermissionsJob extends ClientAppJob
{
    public int $backoff = 5;

    public int $tries = 3;

    /**
     * @param  list<string>  $permissions
     * @param  array<string, list<string>>  $roleAssignments  Role name => its permission names. OmniAuth only uses these the first time it sees this client.
     */
    public function __construct(
        public readonly string $clientId,
        public readonly array $permissions,
        public readonly array $roleAssignments = [],
    ) {}

    public static function queueName(?string $receiver = null): string
    {
        return 'omni-sync-permissions';
    }

    public static function sentByOmniAuth(): bool
    {
        return false;
    }

    public static function sentByClientApps(): bool
    {
        return true;
    }
}
