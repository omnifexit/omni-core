<?php

namespace Omnifexit\OmniCore\Jobs\Message;

use Omnifexit\OmniCore\Jobs\ServiceJob;

/**
 * A Discord message (optionally with one embed) to a channel configured in OmniMessage.
 */
class SendDiscordMessageJob extends ServiceJob
{
    public function __construct(
        public readonly string $channel,
        public readonly string $text,
        public readonly string $dispatchId,
        public readonly ?array $embed = null,
    ) {}

    public static function senderKeys(): array
    {
        return [
            'omniauth' => 'omniauth-omnimessage',
            'omnidesk' => 'omnidesk-omnimessage',
            'omnisales' => 'omnisales-omnimessage',
            'omniai' => 'omniai-omnimessage',
        ];
    }

    /**
     * The Redis hash the receiver writes this job's progress to (DispatchStatus::mark()), for the sender to poll.
     */
    public function statusKey(): string
    {
        return "message:dispatch:{$this->dispatchId}";
    }
}
