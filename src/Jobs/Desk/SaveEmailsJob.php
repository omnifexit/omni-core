<?php

namespace Omnifexit\OmniCore\Jobs\Desk;

use Omnifexit\OmniCore\Jobs\ServiceJob;

/**
 * Emails OmniMail fetched from the inbox, for OmniDesk to store.
 */
class SaveEmailsJob extends ServiceJob
{
    public function __construct(
        public readonly array $emails,
    ) {}

    public static function senderKeys(): array
    {
        return ['omnimail' => 'omnidesk-omnimail'];
    }
}
