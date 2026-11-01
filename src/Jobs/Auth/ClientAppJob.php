<?php

namespace Omnifexit\OmniCore\Jobs\Auth;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use LogicException;
use Omnifexit\OmniCore\Auth\ClientQueueKeys;
use Omnifexit\OmniCore\Jobs\ServiceJob;

/**
 * A job between OmniAuth and one of its client apps (OmniDesk, OmniSales, ...), always about one OAuth client
 * ($clientId). Each app shares its own key with OmniAuth, "omniauth-{app}": an app encrypts with its key, and
 * OmniAuth picks the key of the client the job is about. OmniAuth also refuses a job about one client that arrived
 * on another client's key, so an app can't act on another app's users or roles.
 *
 * @property-read string|null $clientId
 */
abstract class ClientAppJob extends ServiceJob
{
    /**
     * Whether OmniAuth may send this job (to a client app, or to itself).
     */
    abstract public static function sentByOmniAuth(): bool;

    /**
     * Whether client apps may send this job (to OmniAuth).
     */
    abstract public static function sentByClientApps(): bool;

    public static function senderKeys(): array
    {
        return [];
    }

    public function getEncryptionKey(): string
    {
        $service = config('omni-core.service');

        if ($service === 'omniauth') {
            static::sentByOmniAuth() || throw new LogicException(static::class.' is only sent by client apps, not by OmniAuth.');

            return app(ClientQueueKeys::class)->forClient($this->clientId);
        }

        if (! $service) {
            throw new LogicException(static::class.' needs omni-core.service (OMNI_SERVICE) to pick its key.');
        }

        static::sentByClientApps() || throw new LogicException(static::class.' is only sent by OmniAuth, not by ['.$service.'].');

        return "omniauth-{$service}";
    }

    public function acceptedEncryptionKeys(): array
    {
        $service = config('omni-core.service');

        return $service === 'omniauth' ? app(ClientQueueKeys::class)->all() : ["omniauth-{$service}"];
    }

    public function handle(Container $container): void
    {
        if (config('omni-core.service') === 'omniauth' && ! $this->arrivedOnItsClientsKey()) {
            Log::warning(static::class.': arrived on another client\'s queue key, discarding', [
                'clientId' => $this->clientId,
                'key' => $this->receivedEncryptionKey(),
            ]);

            return;
        }

        parent::handle($container);
    }

    private function arrivedOnItsClientsKey(): bool
    {
        // Dispatched in-process (dispatchSync): never went through the queue, so there is no key to check.
        if ($this->receivedEncryptionKey() === null) {
            return true;
        }

        return rescue(
            fn () => app(ClientQueueKeys::class)->forClient($this->clientId) === $this->receivedEncryptionKey(),
            false,
            report: false,
        );
    }
}
