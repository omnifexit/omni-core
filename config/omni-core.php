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

];
