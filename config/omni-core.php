<?php

return [

    /*
    | This service's name as used in the queue key table of the shared jobs (omniauth, omnidesk, omnisales,
    | omnimail, omnimessage, omniai, ...). A shared job uses it to pick the key it is sent with.
    */

    'service' => env('OMNI_SERVICE'),

    /*
    | Shared job class => the class in this service that does the work, for every shared job this service
    | receives. Services that only send a job leave it out.
    |
    | Example: \Omnifexit\OmniCore\Jobs\Mail\SendWelcomeEmailJob::class => \App\JobHandlers\SendWelcomeEmail::class,
    */

    'handlers' => [],

    /*
    | Reported exceptions and jobs that failed for good, posted to Discord.
    */

    'alerts' => [
        // On in production unless turned off, so local errors and tests don't post anywhere.
        'enabled' => env('OMNI_ALERTS_ENABLED', env('APP_ENV') === 'production'),

        // The same error posts once per this many minutes.
        'throttle_minutes' => 10,

        // Used when the queue to OmniMessage is down, and always by services that have no queue to it.
        'webhooks' => [
            'errors' => env('DISCORD_WEBHOOK_ERRORS'),
            'failed-jobs' => env('DISCORD_WEBHOOK_FAILED_JOBS'),
        ],
    ],

    /*
    | OmniAuth SSO settings for client apps (OmniDesk, OmniSales, ...).
    */

    'sso' => [
        // Prefix for the launcher and user-revocation cache keys (e.g. desk:launcher:..., desk:revoke_user:...).
        // Defaults to the service name without a leading "omni" (omnidesk -> desk).
        'cache_prefix' => null,

        // Extra User columns SyncUsers copies from the UserData payload (e.g. ['discord_id']).
        'user_fields' => [],

        // All permission names this app uses (e.g. App\Support\Permissions::ALL). The omni-core:sync-permissions
        // command sends them to OmniAuth on every deploy. This list decides which permissions exist; OmniAuth decides
        // which roles have them.
        'permissions' => [],
    ],

];
