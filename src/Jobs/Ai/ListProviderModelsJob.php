<?php

namespace Omnifexit\OmniCore\Jobs\Ai;

use Omnifexit\OmniCore\Jobs\ServiceJob;

/**
 * Lists the models a provider offers. The list is written to statusKey() as JSON in `models`.
 */
class ListProviderModelsJob extends ServiceJob
{
    public function __construct(
        public readonly string $dispatchId,
        public readonly string $provider,
    ) {}

    public static function senderKeys(): array
    {
        return ['omnidesk' => 'omnidesk-omniai'];
    }

    /**
     * The Redis hash the receiver writes this job's progress to (DispatchStatus::mark()), for the sender to poll.
     */
    public function statusKey(): string
    {
        return "ai:dispatch:{$this->dispatchId}";
    }
}
