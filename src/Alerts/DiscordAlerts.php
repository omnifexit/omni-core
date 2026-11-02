<?php

namespace Omnifexit\OmniCore\Alerts;

use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Omnifexit\OmniCore\Jobs\Message\SendDiscordMessageJob;
use Omnifexit\OmniCore\Sso\CacheKeys;
use Throwable;

/**
 * Posts reported exceptions to the errors channel and jobs that failed for good to the failed-jobs channel. Goes
 * through OmniMessage's queue when this service can, and straight to a Discord webhook when it can't or the queue is
 * down. Sending an alert never throws: it runs while another error is being handled.
 */
class DiscordAlerts
{
    private ?Throwable $jobAttemptException = null;

    private bool $sending = false;

    /**
     * Laravel reports every failed job attempt, including ones it will retry, with the same exception object it just
     * fired this event with. Remembering it keeps those out of the errors channel: a job only alerts through failedJob().
     */
    public function jobAttemptFailed(JobExceptionOccurred $event): void
    {
        $this->jobAttemptException = $event->exception;
    }

    public function exceptionReported(Throwable $exception): void
    {
        if ($exception === $this->jobAttemptException) {
            return;
        }

        $this->send('errors', $this->fingerprint($exception), $this->describeError($exception));
    }

    public function jobFailed(JobFailed $event): void
    {
        $name = $event->job->resolveName();

        $text = '**'.$this->source()."**: job failed for good: `{$name}` on `{$event->job->getQueue()}`\n"
            .$this->describeException($event->exception);

        // A broken queue path must not alert through that same path.
        $this->send('failed-jobs', $name.$this->fingerprint($event->exception), $text, viaQueue: $name !== SendDiscordMessageJob::class);
    }

    private function send(string $channel, string $fingerprint, string $text, bool $viaQueue = true): void
    {
        if ($this->sending || ! config('omni-core.alerts.enabled')) {
            return;
        }

        $this->sending = true;

        try {
            if (! $this->firstInWindow($fingerprint)) {
                return;
            }

            // Discord allows 2000 characters, and a message must not be able to ping anyone.
            $text = Str::limit(str_replace('@', "@\u{200B}", $text), 1900);

            if (! $viaQueue || ! $this->sentThroughQueue($channel, $text)) {
                $this->sendToWebhook($channel, $text);
            }
        } catch (Throwable $e) {
            rescue(fn () => Log::warning('Could not send a Discord alert', ['channel' => $channel, 'error' => $e->getMessage()]), report: false);
        } finally {
            $this->sending = false;
        }
    }

    /**
     * Falls back to the file cache when the default store is down, because then every request fails and would alert.
     * Without any cache it still alerts: too many messages beat none.
     */
    private function firstInWindow(string $fingerprint): bool
    {
        $key = CacheKeys::prefix().':alert:'.hash('xxh128', $fingerprint);
        $ttl = now()->addMinutes((int) config('omni-core.alerts.throttle_minutes', 10));

        try {
            return Cache::add($key, true, $ttl);
        } catch (Throwable) {
            try {
                return Cache::store('file')->add($key, true, $ttl);
            } catch (Throwable) {
                return true;
            }
        }
    }

    private function sentThroughQueue(string $channel, string $text): bool
    {
        $canSend = config('queue.connections.message_service') !== null
            && array_key_exists((string) config('omni-core.service'), SendDiscordMessageJob::senderKeys());

        if (! $canSend) {
            return false;
        }

        try {
            SendDiscordMessageJob::dispatch(channel: $channel, text: $text, dispatchId: (string) Str::uuid())
                ->onConnection('message_service')
                ->onQueue(SendDiscordMessageJob::queueName());

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function sendToWebhook(string $channel, string $text): void
    {
        $webhook = config("omni-core.alerts.webhooks.{$channel}");

        if (! $webhook) {
            return;
        }

        Http::timeout(3)->post($webhook, ['content' => $text, 'allowed_mentions' => ['parse' => []]]);
    }

    private function describeError(Throwable $exception): string
    {
        $where = app()->runningInConsole() || ! app()->bound('request')
            ? 'console'
            : '`'.request()->method().' /'.ltrim(request()->path(), '/').'`';

        return '**'.$this->source()."**: error in {$where}\n".$this->describeException($exception);
    }

    private function describeException(Throwable $exception): string
    {
        $file = Str::after($exception->getFile(), base_path().DIRECTORY_SEPARATOR);

        return '`'.$exception::class.'` at `'.$file.':'.$exception->getLine()."`\n"
            .Str::limit($exception->getMessage(), 1000);
    }

    private function source(): string
    {
        return (string) (config('omni-core.service') ?: config('app.name'));
    }

    /**
     * Numbers are left out, so the same error about record 5 and record 6 counts as one.
     */
    private function fingerprint(Throwable $exception): string
    {
        return $exception::class.$exception->getFile().$exception->getLine().preg_replace('/\d+/', '#', $exception->getMessage());
    }
}
