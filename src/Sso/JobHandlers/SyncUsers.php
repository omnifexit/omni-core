<?php

namespace Omnifexit\OmniCore\Sso\JobHandlers;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Omnifexit\OmniCore\Jobs\Auth\SyncUsersJob;
use Omnifexit\OmniCore\Sso\Data\UserData;
use Omnifexit\OmniCore\Sso\RevokedUsers;
use Throwable;

/**
 * Mirrors the users OmniAuth sends (with their roles for this app); users no longer in the list are removed and logged out.
 */
class SyncUsers
{
    /**
     * @throws Throwable
     */
    public function handle(SyncUsersJob $job): void
    {
        if ($job->isRequest()) {
            return;
        }

        $lock = Cache::lock('omniauth_sync_lock', 600);
        if (! $lock->get()) {
            return;
        }

        /** @var class-string<Model> $userModel */
        $userModel = config('auth.providers.users.model', 'App\\Models\\User');
        /** @var list<string> $extraFields */
        $extraFields = config('omni-core.sso.user_fields', []);

        $toRevoke = [];

        try {
            DB::transaction(function () use (&$toRevoke, $job, $userModel, $extraFields) {
                $rawUsers = $job->usersData ?? [];
                $users = collect($rawUsers)->map(fn ($u) => UserData::from($u));
                $remoteIds = $users->pluck('id')->toArray();

                foreach ($users as $index => $userData) {
                    $rawUser = $rawUsers[$index] ?? [];

                    $existingUser = $userModel::withTrashed()
                        ->where('omniauth_id', $userData->id)
                        ->orWhere('email', $userData->email)
                        ->first();

                    $values = [
                        'password' => $existingUser->password ?? Hash::make(Str::random(32)),
                        'email_verified_at' => $this->parseDate($userData->email_verified_at),
                        'omniauth_id' => $userData->id,
                        'name' => $userData->name,
                    ];

                    foreach ($extraFields as $field) {
                        $values[$field] = property_exists($userData, $field)
                            ? $userData->{$field}
                            : ($rawUser[$field] ?? null);
                    }

                    $user = $userModel::withTrashed()->updateOrCreate(
                        ['email' => $userData->email],
                        $values,
                    );

                    if ($user->trashed()) {
                        $user->restore();
                    }

                    $newRoles = $userData->roles->toCollection()->pluck('name')->sort()->values()->toArray();
                    $oldRoles = $user->getRoleNames()->sort()->values()->toArray();

                    $user->syncRoles($newRoles);

                    if ($oldRoles !== $newRoles) {
                        $toRevoke[] = $user->id;
                    }
                }

                $deletedIds = $userModel::when(
                    $job->triggeringUserId,
                    fn ($q) => $q->where('omniauth_id', '!=', $job->triggeringUserId)
                )->where(function ($query) use ($remoteIds) {
                    $query->whereNotIn('omniauth_id', $remoteIds)
                        ->orWhereNull('omniauth_id');
                })->pluck('id')->all();

                $toRevoke = array_merge($toRevoke, $deletedIds);

                if ($job->triggeringUserId && ! in_array((string) $job->triggeringUserId, $remoteIds)) {
                    $triggeringLocalId = $userModel::where('omniauth_id', $job->triggeringUserId)->value('id');
                    if ($triggeringLocalId) {
                        $toRevoke[] = $triggeringLocalId;
                    }
                }

                $userModel::whereIn('id', $deletedIds)->delete();
            });
        } catch (Exception $e) {
            Log::error('SyncUsersJob failed', [
                'service' => config('omni-core.service'),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } finally {
            $lock->release();
        }

        foreach ($toRevoke as $userId) {
            RevokedUsers::flag($userId);
        }
    }

    protected function parseDate(mixed $date): ?string
    {
        if (is_array($date) && isset($date['date'])) {
            return $date['date'];
        }

        return $date;
    }
}
