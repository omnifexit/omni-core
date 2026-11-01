<?php

namespace Omnifexit\OmniCore\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use LogicException;
use Omnifexit\OmniCore\Queue\EncryptsWithQueueKey;

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
        $handlers = config('omni-core.handlers', []);

        // A service may extend a shared job (e.g. to keep an old class name while it moves here); the handler is
        // registered for the shared class.
        $handler = collect([static::class, ...array_values(class_parents($this))])
            ->map(fn (string $class) => $handlers[$class] ?? null)
            ->first(fn (?string $handler) => $handler !== null)
            ?? throw new LogicException(static::class.' has no handler in this service (config/omni-core.php, \'handlers\'). Is it on the right queue?');

        $container->call([$container->make($handler), 'handle'], ['job' => $this]);
    }
}
