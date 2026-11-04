<?php

namespace Omnifexit\OmniCore\Sso\JobHandlers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Omnifexit\OmniCore\Jobs\Auth\LogOutUserJob;
use Omnifexit\OmniCore\Sso\RevokedUsers;

/**
 * Flags the user, so EnsureUserNotRevoked logs them out on their next request.
 */
class LogOutUser
{
    public function handle(LogOutUserJob $job): void
    {
        if (! $job->clientId || $job->clientId !== config('services.omniauth.client_id')) {
            return;
        }

        /** @var class-string<Model> $userModel */
        $userModel = config('auth.providers.users.model');
        $userId = $userModel::where('omniauth_id', $job->omniAuthUserId)->value('id');

        if ($userId !== null) {
            // A "remember me" cookie would start a new session, which the flag doesn't reach.
            $userModel::whereKey($userId)->update(['remember_token' => Str::random(60)]);

            RevokedUsers::flag($userId);
        }
    }
}
