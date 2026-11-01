<?php

namespace Omnifexit\OmniCore\Jobs\Ai;

use Omnifexit\OmniCore\Jobs\ServiceJob;

/**
 * A prompt for a cloud AI provider (OpenAI, Gemini, Claude). The result is written to statusKey().
 */
class RunCloudPromptJob extends ServiceJob
{
    public function __construct(
        public readonly string $dispatchId,
        public readonly string $prompt,
        public readonly string $provider,
        public readonly ?string $model,
        public readonly ?string $system,
        public readonly bool $json,
        public readonly ?int $timeout,
        public readonly string $callerId,
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
