<?php

use Omnifexit\OmniCore\Auth\ClientQueueKeys;
use Omnifexit\OmniCore\Jobs\Auth\RequestUserSyncJob;
use Omnifexit\OmniCore\Jobs\Auth\SyncPermissionsJob;
use Omnifexit\OmniCore\Jobs\Auth\SyncRoleChangeJob;
use Omnifexit\OmniCore\Jobs\Auth\SyncUsersJob;

class FakeClientQueueKeys implements ClientQueueKeys
{
    public function forClient(?string $clientId): string
    {
        return match ($clientId) {
            'desk-client' => 'omniauth-omnidesk',
            'sales-client' => 'omniauth-omnisales',
            default => throw new RuntimeException("No client [{$clientId}]"),
        };
    }

    public function all(): array
    {
        return ['omniauth-omnidesk', 'omniauth-omnisales'];
    }
}

class RecordingSyncHandler
{
    public static array $handled = [];

    public function handle(SyncUsersJob $job): void
    {
        self::$handled[] = $job;
    }
}

// An app's thin subclass that keeps its old class name during a move to omni-core.
class LegacyAppSyncUsersJob extends SyncUsersJob {}

beforeEach(function () {
    config()->set('queue.keys.omniauth-omnidesk', queueKey());
    config()->set('queue.keys.omniauth-omnisales', queueKey());
    app()->instance(ClientQueueKeys::class, new FakeClientQueueKeys);
    RecordingSyncHandler::$handled = [];
});

function asService(string $service): void
{
    config()->set('omni-core.service', $service);
}

it('is encrypted with the app\'s own key when an app sends it', function () {
    asService('omnidesk');

    expect(serialize(new SyncUsersJob(clientId: 'desk-client')))->toContain(serialize('omniauth-omnidesk'));
});

it('is encrypted with the target client\'s key when OmniAuth sends it', function () {
    asService('omniauth');

    expect(serialize(new SyncUsersJob(clientId: 'sales-client', usersData: [])))->toContain(serialize('omniauth-omnisales'));
});

it('accepts only its own key in an app and every client key in OmniAuth', function () {
    asService('omnisales');
    expect((new SyncUsersJob)->acceptedEncryptionKeys())->toBe(['omniauth-omnisales']);

    asService('omniauth');
    expect((new SyncUsersJob)->acceptedEncryptionKeys())->toBe(['omniauth-omnidesk', 'omniauth-omnisales']);
});

it('enforces who may send which job', function () {
    asService('omnidesk');
    expect(fn () => serialize(new SyncRoleChangeJob(clientId: 'desk-client', permissions: [], roleName: 'admin')))
        ->toThrow(LogicException::class, 'only sent by OmniAuth');

    asService('omniauth');
    expect(fn () => serialize(new SyncPermissionsJob(clientId: 'desk-client', permissions: [])))
        ->toThrow(LogicException::class, 'only sent by client apps');
});

it('lets OmniAuth discard a job about one client that arrived on another client\'s key', function () {
    asService('omnidesk');
    $payload = serialize(new SyncUsersJob(clientId: 'sales-client')); // OmniDesk asking about OmniSales' users

    asService('omniauth');
    config()->set('omni-core.handlers', [SyncUsersJob::class => RecordingSyncHandler::class]);
    app()->call([unserialize($payload), 'handle']);

    expect(RecordingSyncHandler::$handled)->toBeEmpty();
});

it('lets OmniAuth handle a job that arrived on its own client\'s key', function () {
    asService('omnidesk');
    $payload = serialize(new SyncUsersJob(clientId: 'desk-client'));

    asService('omniauth');
    config()->set('omni-core.handlers', [SyncUsersJob::class => RecordingSyncHandler::class]);
    $job = unserialize($payload);
    app()->call([$job, 'handle']);

    expect($job->receivedEncryptionKey())->toBe('omniauth-omnidesk')
        ->and(RecordingSyncHandler::$handled)->toHaveCount(1)
        ->and($job->isRequest())->toBeTrue();
});

it('finds the handler of the shared class for an app\'s legacy subclass', function () {
    asService('omnidesk');
    config()->set('omni-core.handlers', [SyncUsersJob::class => RecordingSyncHandler::class]);

    $job = unserialize(serialize(new LegacyAppSyncUsersJob(clientId: 'desk-client', usersData: [['id' => 1]])));
    app()->call([$job, 'handle']);

    expect(RecordingSyncHandler::$handled)->toHaveCount(1)
        ->and(serialize($job))->toContain(LegacyAppSyncUsersJob::class);
});

it('keeps the received key out of the payload', function () {
    asService('omnidesk');
    $job = unserialize(serialize(new RequestUserSyncJob(clientId: 'desk-client', dispatchId: 'd-1')));

    expect($job->receivedEncryptionKey())->toBe('omniauth-omnidesk')
        ->and(unserialize(serialize($job))->receivedEncryptionKey())->toBe('omniauth-omnidesk')
        ->and($job->statusKey())->toBe('sync:dispatch:d-1');
});
