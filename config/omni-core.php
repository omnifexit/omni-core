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
    | OmniAuth SSO settings for client apps (OmniDesk, OmniSales, ...).
    */

    'sso' => [
        // Prefix for the launcher and user-revocation cache keys (e.g. desk:launcher:..., desk:revoke_user:...).
        // Defaults to the service name without a leading "omni" (omnidesk -> desk).
        'cache_prefix' => null,

        // Extra User columns SyncUsers copies from the UserData payload (e.g. ['discord_id']).
        'user_fields' => [],

        // All permission names this app uses (e.g. App\Support\Permissions::ALL). When non-empty,
        // SsoController dispatches SyncPermissionsJob on login so OmniAuth learns new permissions.
        'permissions' => [],
    ],

];
