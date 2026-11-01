<?php

namespace Omnifexit\OmniCore\Jobs\Ai;

use Omnifexit\OmniCore\Jobs\ServiceJob;

/**
 * A prompt for the local Ollama model. The result is written to statusKey().
 */
class RunLocalPromptJob extends ServiceJob
{
    public function __construct(
        public readonly string $dispatchId,
        public readonly string $prompt,
        public readonly ?string $system = null,
        public readonly bool $json = false,
        public readonly ?int $timeout = null,
    ) {}

    public static function queue(?string $receiver = null): string
    {
        return 'ai-prompt-local-queue';
    }

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
