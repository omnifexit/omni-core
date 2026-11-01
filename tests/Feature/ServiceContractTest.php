<?php

use Omnifexit\OmniCore\Jobs\Auth\SyncRoleChangeJob;
use Omnifexit\OmniCore\Jobs\Auth\SyncUsersJob;
use Omnifexit\OmniCore\Jobs\Mail\FetchMailsJob;
use Omnifexit\OmniCore\Jobs\Mail\SendWelcomeEmailJob;
use Omnifexit\OmniCore\Testing\ServiceContract;

class ContractWelcomeHandler
{
    public function handle(SendWelcomeEmailJob $job): void {}
}

class ContractWrongTypeHandler
{
    public function handle(FetchMailsJob $job): void {}
}

beforeEach(function () {
    config()->set('omni-core.service', 'omnimail');
    config()->set('horizon.defaults', ['supervisor-1' => ['queue' => ['verification-queue']]]);
    config()->set('horizon.environments', []);
});

it('passes for a correctly wired service', function () {
    config()->set('omni-core.handlers', [SendWelcomeEmailJob::class => ContractWelcomeHandler::class]);

    expect(ServiceContract::problems())->toBe([]);
});

it('reports a missing service name', function () {
    config()->set('omni-core.service', null);

    expect(ServiceContract::problems()[0])->toContain('no service name');
});

it('reports a handler that does not exist or takes the wrong job', function () {
    config()->set('omni-core.handlers', [
        SendWelcomeEmailJob::class => ContractWrongTypeHandler::class,
        FetchMailsJob::class => 'App\JobHandlers\Missing',
    ]);
    config()->set('horizon.defaults', ['supervisor-1' => ['queue' => ['verification-queue', 'mail-fetch-queue']]]);

    expect(ServiceContract::problems())->toBe([
        'ContractWrongTypeHandler::handle() must take the job as its first argument ('.SendWelcomeEmailJob::class.' $job).',
        'The handler [App\JobHandlers\Missing] for '.FetchMailsJob::class.' doesn\'t exist.',
    ]);
});

it('reports a handled job whose queue Horizon does not listen on', function () {
    config()->set('omni-core.handlers', [SendWelcomeEmailJob::class => ContractWelcomeHandler::class]);
    config()->set('horizon.defaults', ['supervisor-1' => ['queue' => ['default']]]);

    expect(ServiceContract::problems()[0])->toContain('arrives on the [verification-queue] queue, but no Horizon supervisor');
});

it('also counts queues of environment-specific supervisors', function () {
    config()->set('omni-core.handlers', [SendWelcomeEmailJob::class => ContractWelcomeHandler::class]);
    config()->set('horizon.defaults', ['supervisor-1' => ['queue' => ['default']]]);
    config()->set('horizon.environments', ['production' => ['supervisor-2' => ['queue' => ['verification-queue']]]]);

    expect(ServiceContract::problems())->toBe([]);
});

it('knows the per-app queues of the sync jobs', function () {
    expect(SyncUsersJob::queueName('omniauth'))->toBe('omni-sync-request')
        ->and(SyncUsersJob::queueName('omnidesk'))->toBe('omnidesk-sync-response')
        ->and(SyncRoleChangeJob::queueName('omnisales'))->toBe('omnisales-sync-queue')
        ->and(SendWelcomeEmailJob::queueName())->toBe('verification-queue');
});
