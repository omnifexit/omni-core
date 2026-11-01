<?php

namespace Omnifexit\OmniCore\Jobs\Mail;

use Omnifexit\OmniCore\Jobs\ServiceJob;

/**
 * Contract signing link from OmniSales (DocuSeal).
 */
class SendContractSigningEmailJob extends ServiceJob
{
    public function __construct(
        public readonly string $email,
        public readonly string $name,
        public readonly string $reference,
        public readonly string $signingUrl,
        public readonly string $dispatchId,
    ) {}

    public static function queue(?string $receiver = null): string
    {
        return 'contract-signing-queue';
    }

    public static function senderKeys(): array
    {
        return ['omnisales' => 'omnisales-omnimail'];
    }

    /**
     * The Redis hash the receiver writes this job's progress to (DispatchStatus::mark()), for the sender to poll.
     */
    public function statusKey(): string
    {
        return "mail:dispatch:{$this->dispatchId}";
    }
}
