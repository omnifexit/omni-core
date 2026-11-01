<?php

namespace Omnifexit\OmniCore\Jobs\Mail;

use Omnifexit\OmniCore\Jobs\ServiceJob;

/**
 * Counts sent/failed/received mail in OmniMail's Redis stats. OmniAuth sends it; OmniMail runs it with dispatchSync,
 * which still serializes the job, so OmniMail encrypts it with the same key.
 */
class UpdateMailAnalytics extends ServiceJob
{
    public function __construct(
        public readonly string $type,
        public readonly int $count = 1,
    ) {
        $this->onQueue('analytics-mail-queue');
    }

    public static function queueName(?string $receiver = null): string
    {
        return 'analytics-mail-queue';
    }

    public static function senderKeys(): array
    {
        return [
            'omniauth' => 'omniauth-omnimail',
            'omnimail' => 'omniauth-omnimail',
        ];
    }
}
