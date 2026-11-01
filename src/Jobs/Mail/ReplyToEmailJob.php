<?php

namespace Omnifexit\OmniCore\Jobs\Mail;

use Omnifexit\OmniCore\Jobs\ServiceJob;

/**
 * A reply from OmniDesk to an incoming email.
 */
class ReplyToEmailJob extends ServiceJob
{
    public function __construct(
        public readonly string $in_reply_to,
        public readonly string $to_address,
        public readonly string $subject,
        public readonly string $body,
        public readonly ?string $headerTitle = null,
        public readonly ?string $userPhone = null,
        public readonly ?string $userName = null,
    ) {}

    public static function senderKeys(): array
    {
        return ['omnidesk' => 'omnidesk-omnimail'];
    }
}
