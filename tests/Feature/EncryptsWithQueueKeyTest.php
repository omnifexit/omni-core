<?php

use Omnifexit\OmniCore\Queue\EncryptsWithQueueKey;

class PairwiseJob
{
    use EncryptsWithQueueKey;

    public function __construct(
        public readonly string $email,
        public readonly array $payload = [],
    ) {}

    public function getEncryptionKey(): string
    {
        return 'omnidesk-omnimail';
    }
}

// A job two services send: each sender encrypts with its own key (simulated by $sender).
class SharedJob extends PairwiseJob
{
    public static string $sender = 'omnidesk-omnimail';

    public function getEncryptionKey(): string
    {
        return self::$sender;
    }

    public function acceptedEncryptionKeys(): array
    {
        return ['omnidesk-omnimail', 'omnisales-omnimail'];
    }
}

beforeEach(function () {
    config()->set('queue.keys.omnidesk-omnimail', queueKey());
    config()->set('queue.keys.omnisales-omnimail', queueKey());
});

it('round-trips a job encrypted with its own pairwise key', function () {
    $restored = unserialize(serialize(new PairwiseJob('a@example.test', ['n' => 1])));

    expect($restored->email)->toBe('a@example.test')
        ->and($restored->payload)->toBe(['n' => 1]);
});

it('never puts the properties on the queue in plain text', function () {
    expect(serialize(new PairwiseJob('secret@example.test')))->not->toContain('secret@example.test');
});

it('refuses to serialize when its key is not configured', function () {
    config()->set('queue.keys.omnidesk-omnimail', null);

    expect(fn () => serialize(new PairwiseJob('a@example.test')))
        ->toThrow(Exception::class, 'Queue encryption key [omnidesk-omnimail] not found.');
});

it('rejects a job claiming a key it does not accept', function () {
    $job = new PairwiseJob('a@example.test');
    $forged = str_replace(serialize('omnidesk-omnimail'), serialize('omnisales-omnimail'), serialize($job));

    expect(fn () => unserialize($forged))->toThrow(Exception::class, 'does not accept queue key [omnisales-omnimail]');
});

it('rejects an unencrypted payload', function () {
    $plain = 'O:'.strlen(PairwiseJob::class).':"'.PairwiseJob::class.'":1:{s:5:"email";s:1:"x";}';

    expect(fn () => unserialize($plain))->toThrow(Exception::class, 'Unencrypted job detected');
});

it('accepts every key a shared job lists', function () {
    SharedJob::$sender = 'omnisales-omnimail';
    $fromSales = serialize(new SharedJob('a@example.test'));
    SharedJob::$sender = 'omnidesk-omnimail';

    expect(unserialize($fromSales)->email)->toBe('a@example.test');
});
