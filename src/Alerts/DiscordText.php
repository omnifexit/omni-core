<?php

namespace Omnifexit\OmniCore\Alerts;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Small helpers for the Dutch messages the apps post to Discord.
 */
class DiscordText
{
    /**
     * Text someone typed can't ping anyone or break out of its line.
     */
    public static function safe(?string $text, int $limit = 200): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $text)));

        return Str::limit(str_replace('@', "@\u{200B}", $text), $limit);
    }

    /**
     * Like "5 nov 14:30", in Dutch time.
     */
    public static function date(?DateTimeInterface $date): string
    {
        return $date
            ? Carbon::instance($date)->setTimezone('Europe/Amsterdam')->locale('nl')->translatedFormat('j M H:i')
            : '-';
    }

    /**
     * Turns `@Name` in text that went through safe() into a real mention, for people who linked Discord.
     *
     * @param  iterable<array{name: string, discord_id: ?string}>  $people
     */
    public static function mentions(string $safeText, iterable $people): string
    {
        foreach ($people as $person) {
            if ($person['discord_id']) {
                $safeText = (string) preg_replace(
                    '/@\x{200B}'.preg_quote($person['name'], '/').'\b/iu',
                    "<@{$person['discord_id']}>",
                    $safeText,
                );
            }
        }

        return $safeText;
    }

    /**
     * Like "1 afspraak" or "3 afspraken".
     */
    public static function count(int $count, string $one, string $many): string
    {
        return $count.' '.($count === 1 ? $one : $many);
    }

    /**
     * Joins the lines that have something to say.
     *
     * @param  array<int, string|null>  $lines
     */
    public static function lines(array $lines): string
    {
        return implode("\n", array_filter($lines, fn (?string $line) => $line !== null && $line !== ''));
    }
}
