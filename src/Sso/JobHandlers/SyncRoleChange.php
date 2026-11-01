<?php

namespace Omnifexit\OmniCore\Sso\JobHandlers;

use Omnifexit\OmniCore\Jobs\Auth\SyncRoleChangeJob;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Mirrors a role created, changed or deleted in OmniAuth, with its permissions.
 */
class SyncRoleChange
{
    public function handle(SyncRoleChangeJob $job): void
    {
        if (! $job->clientId || $job->clientId !== config('services.omniauth.client_id')) {
            return;
        }

        if ($job->isDeleted) {
            Role::where('name', $job->roleName)
                ->where('guard_name', 'web')
                ->delete();

            return;
        }

        $role = Role::firstOrCreate([
            'name' => $job->roleName,
            'guard_name' => 'web',
        ]);

        $permissions = collect($job->permissions)->map(
            fn (string $name) => Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ])
        );

        $role->syncPermissions($permissions);
    }
}
