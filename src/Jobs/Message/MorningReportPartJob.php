<?php

namespace Omnifexit\OmniCore\Jobs\Message;

use Omnifexit\OmniCore\Jobs\ServiceJob;

/**
 * One app's share of the morning overview. OmniMessage combines the parts per person and posts them as one overview.
 */
class MorningReportPartJob extends ServiceJob
{
    /**
     * @param  list<string>  $team
     * @param  list<array{key: string, name: string, discord_id: ?string, lines: list<string>}>  $people
     */
    public function __construct(
        public readonly string $day,
        public readonly string $app,
        public readonly array $team,
        public readonly array $people,
    ) {}

    public static function queueName(?string $receiver = null): string
    {
        return 'discord-queue';
    }

    public static function senderKeys(): array
    {
        return [
            'omnidesk' => 'omnidesk-omnimessage',
            'omnisales' => 'omnisales-omnimessage',
        ];
    }
}
