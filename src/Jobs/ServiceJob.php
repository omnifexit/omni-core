<?php

namespace Omnifexit\OmniCore\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use LogicException;
use Omnifexit\OmniCore\Queue\EncryptsWithQueueKey;
use Throwable;

/**
 * A job sent from one service to another. The same class is used on both sides, so sender and receiver can't drift
 * apart. The receiver does the work in a handler registered in its config/omni-core.php ('handlers'); handle()
 * passes the job to it.
 */
abstract class ServiceJob implements ShouldQueue
{
    use Dispatchable, EncryptsWithQueueKey, InteractsWithQueue, Queueable;

    /**
     * The pairwise queue key each sending service encrypts this job with, keyed by service name
     * (config('omni-core.service')). The receiver accepts exactly these keys.
     *
     * @return array<string, string>
     */
    abstract public static function senderKeys(): array;

    /**
     * The queue this job travels on to $receiver (a service name, e.g. omnimail). Senders dispatch with
     * ->onQueue(TheJob::queueName(...)) and the receiving service's Horizon must listen on it; ServiceContract checks
     * the latter. Most jobs have one receiver, so the argument only matters for jobs that go to several. (Not named
     * queue(): Laravel calls a job's queue() method to push it.)
     */
    abstract public static function queueName(?string $receiver = null): string;

    public function getEncryptionKey(): string
    {
        $service = config('omni-core.service');

        return static::senderKeys()[$service] ?? throw new LogicException(sprintf(
            '%s may not be sent by [%s]. Allowed senders: %s. Set omni-core.service (OMNI_SERVICE) or add the service to senderKeys().',
            static::class,
            $service ?: 'no omni-core.service set',
            implode(', ', array_keys(static::senderKeys())),
        ));
    }

    /**
     * @return list<string>
     */
    public function acceptedEncryptionKeys(): array
    {
        return array_values(array_unique(static::senderKeys()));
    }

    public function handle(Container $container): void
    {
        $container->call([$container->make($this->handlerClass()), 'handle'], ['job' => $this]);
    }

    /**
     * Called by Laravel once, after the last attempt failed. Passed on to the handler's failed($job, $exception) when
     * it has one, e.g. to count the failure once instead of on every retry.
     */
    public function failed(?Throwable $exception): void
    {
        $handler = rescue(fn () => app($this->handlerClass()), report: false);

        if ($handler !== null && method_exists($handler, 'failed')) {
            app()->call([$handler, 'failed'], ['job' => $this, 'exception' => $exception]);
        }
    }

    /**
     * @return class-string
     */
    private function handlerClass(): string
    {
        $handlers = config('omni-core.handlers', []);

        // A service may extend a shared job (e.g. to keep an old class name while it moves here); the handler is
        // registered for the shared class.
        return collect([static::class, ...array_values(class_parents($this))])
            ->map(fn (string $class) => $handlers[$class] ?? null)
            ->first(fn (?string $handler) => $handler !== null)
            ?? throw new LogicException(static::class.' has no handler in this service (config/omni-core.php, \'handlers\'). Is it on the right queue?');
    }
}
