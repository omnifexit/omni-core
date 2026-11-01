<?php

namespace Omnifexit\OmniCore\Jobs\Mail;

use Omnifexit\OmniCore\Jobs\ServiceJob;

/**
 * Welcome email with a temporary password, for a user created in OmniAuth.
 */
class SendWelcomeEmailJob extends ServiceJob
{
    public function __construct(
        public readonly string $email,
        public readonly string $name,
        public readonly string $temporaryPassword,
        public readonly string $dispatchId,
    ) {}

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
