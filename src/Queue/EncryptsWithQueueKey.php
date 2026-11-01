<?php

namespace Omnifexit\OmniCore\Queue;

use Exception;
use Illuminate\Encryption\Encrypter;
use ReflectionObject;

/**
 * Encrypts a queued job's properties with a pairwise key shared by exactly two services
 * (config('queue.keys.<name>'), e.g. 'omnidesk-omnimail'), so only the sender and the receiver
 * can read or forge it. Sender and receiver use the same job class; only the receiver's handle()
 * does the work.
 */
trait EncryptsWithQueueKey
{
    /**
     * @return array{_enc: string, _k: string}
     *
     * @throws Exception
     */
    public function __serialize(): array
    {
        $keyName = $this->getEncryptionKey();
        $key = config(key: "queue.keys.{$keyName}");

        if (! $key) {
            throw new Exception(message: "Queue encryption key [{$keyName}] not found.");
        }

        $reflection = new ReflectionObject(object: $this);
        $properties = [];

        foreach ($reflection->getProperties() as $property) {
            if ($property->isStatic() || $property->isVirtual()) {
                continue;
            }

            if ($property->isInitialized(object: $this)) {
                $properties[$property->getName()] = $property->getValue(object: $this);
            }
        }

        return [
            '_enc' => $this->makeQueueEncrypter(key: $key)->encrypt(value: serialize(value: $properties)),
            '_k' => $keyName,
        ];
    }

    abstract public function getEncryptionKey(): string;

    /**
     * The key names this job may arrive encrypted with. Defaults to its own pairwise key; a job that
     * several services send overrides this. Anything else is rejected, so a service holding some
     * other key can't forge this job.
     *
     * @return list<string>
     */
    public function acceptedEncryptionKeys(): array
    {
        return [$this->getEncryptionKey()];
    }

    private function makeQueueEncrypter(string $key): Encrypter
    {
        return new Encrypter(
            key: base64_decode(string: str_replace(search: 'base64:', replace: '', subject: $key)),
            cipher: 'AES-256-CBC'
        );
    }

    /**
     * @throws Exception
     */
    public function __unserialize(array $data): void
    {
        if (! isset($data['_enc'], $data['_k'])) {
            throw new Exception(message: 'Security Breach: Unencrypted job detected for '.static::class);
        }

        if (! in_array(needle: $data['_k'], haystack: $this->acceptedEncryptionKeys(), strict: true)) {
            throw new Exception(message: 'Rejected: '.static::class." does not accept queue key [{$data['_k']}].");
        }

        $key = config(key: "queue.keys.{$data['_k']}");

        if (! $key) {
            throw new Exception(message: "Decryption failed: Key [{$data['_k']}] not configured.");
        }

        $properties = unserialize(
            data: $this->makeQueueEncrypter(key: $key)
                ->decrypt(payload: $data['_enc'])
        );

        foreach ($properties as $k => $v) {
            $this->$k = $v;
        }
    }
}
