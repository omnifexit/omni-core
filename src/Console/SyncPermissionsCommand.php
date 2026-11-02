<?php

namespace Omnifexit\OmniCore\Console;

use Illuminate\Console\Command;
use Omnifexit\OmniCore\Jobs\Auth\SyncPermissionsJob;
use Spatie\Permission\Models\Role;

/**
 * Tells OmniAuth which permissions this app's code has, so its list matches. Runs once per deploy, as a post-deploy
 * command after the new code is live.
 */
class SyncPermissionsCommand extends Command
{
    protected $signature = 'omni-core:sync-permissions';

    protected $description = 'Send this app\'s permission list to OmniAuth';

    public function handle(): int
    {
        /** @var list<string> $permissions */
        $permissions = config('omni-core.sso.permissions', []);
        $clientId = (string) config('services.omniauth.client_id');

        if ($permissions === []) {
            $this->error('No permissions to sync: omni-core.sso.permissions is empty.');

            return self::FAILURE;
        }

        if ($clientId === '') {
            $this->error('No OmniAuth client: services.omniauth.client_id is empty.');

            return self::FAILURE;
        }

        // OmniAuth only uses these the first time it sees this app, to keep the permissions its roles already have.
        $roleAssignments = Role::with('permissions')
            ->get()
            ->mapWithKeys(fn (Role $role) => [
                $role->name => $role->permissions->pluck('name')->toArray(),
            ])
            ->toArray();

        SyncPermissionsJob::dispatch(
            clientId: $clientId,
            permissions: $permissions,
            roleAssignments: $roleAssignments,
        )->onConnection('redis')->onQueue(SyncPermissionsJob::queueName());

        $this->info('Sent '.count($permissions).' permissions to OmniAuth.');

        return self::SUCCESS;
    }
}
