<?php

namespace Omnifexit\OmniCore\Jobs\Auth;

use Illuminate\Contracts\Queue\ShouldBeUnique;

/**
 * A client app's full permission list (and which permissions its roles have), so OmniAuth knows about new
 * permissions. Sent on login.
 *
 * Unique for a few minutes per client, so a burst of logins sends it once. The lock lives in the sending app's cache
 * and OmniAuth can't release it, so it must expire by itself: without uniqueFor it would be held forever.
 */
class SyncPermissionsJob extends ClientAppJob implements ShouldBeUnique
{
    public int $backoff = 5;

    public int $tries = 3;

    public int $uniqueFor = 300;

    /**
     * @param  list<string>  $permissions
     * @param  array<string, list<string>>  $roleAssignments  Role name => its permission names.
     */
    public function __construct(
        public readonly string $clientId,
        public readonly array $permissions,
        public readonly array $roleAssignments = [],
    ) {}

    public static function sentByOmniAuth(): bool
    {
        return false;
    }

    public static function sentByClientApps(): bool
    {
        return true;
    }

    public function uniqueId(): string
    {
        return $this->clientId;
    }
}
