<?php

namespace Omnifexit\OmniCore\Jobs\Mail;

use Omnifexit\OmniCore\Jobs\ServiceJob;

/**
 * Email-address verification link for an OmniAuth user.
 */
class SendVerificationEmailJob extends ServiceJob
{
    public function __construct(
        public readonly string $email,
        public readonly string $name,
        public readonly string $verificationUrl,
        public readonly string $dispatchId,
    ) {}

    public static function queue(?string $receiver = null): string
    {
        return 'verification-queue';
    }

    public static function senderKeys(): array
    {
        return ['omniauth' => 'omniauth-omnimail'];
    }

    /**
     * The Redis hash the receiver writes this job's progress to (DispatchStatus::mark()), for the sender to poll.
     */
    public function statusKey(): string
    {
        return "mail:dispatch:{$this->dispatchId}";
    }
}
