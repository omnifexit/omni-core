<?php

use Illuminate\Support\Facades\Redis;
use Omnifexit\OmniCore\Jobs\Ai\RunLocalPromptJob;
use Omnifexit\OmniCore\Jobs\Mail\FetchMailsJob;
use Omnifexit\OmniCore\Jobs\Mail\SendWelcomeEmailJob;
use Omnifexit\OmniCore\Jobs\Message\SendDiscordMessageJob;
use Omnifexit\OmniCore\Queue\DispatchStatus;

class RecordingWelcomeHandler
{
    public static ?SendWelcomeEmailJob $handled = null;

    public function handle(SendWelcomeEmailJob $job): void
    {
        self::$handled = $job;
    }
}

beforeEach(function () {
    foreach (['omniauth-omnimail', 'omnidesk-omnimail', 'omnidesk-omnimessage', 'omnisales-omnimessage', 'omnidesk-omniai'] as $key) {
        config()->set("queue.keys.{$key}", queueKey());
    }
});

function welcomeJob(): SendWelcomeEmailJob
{
    return new SendWelcomeEmailJob(email: 'a@example.test', name: 'A', temporaryPassword: 'secret-pw', dispatchId: 'd-1');
}

it('encrypts with the key of the service that sends it', function () {
    config()->set('omni-core.service', 'omnidesk');
    $fromDesk = serialize(new SendDiscordMessageJob('alerts', 'hi', 'd-1'));

    config()->set('omni-core.service', 'omnisales');
    $fromSales = serialize(new SendDiscordMessageJob('alerts', 'hi', 'd-1'));

    expect($fromDesk)->toContain(serialize('omnidesk-omnimessage'))
        ->and($fromSales)->toContain(serialize('omnisales-omnimessage'))
        ->and(unserialize($fromSales)->text)->toBe('hi');
});

it('refuses to be sent by a service that is not a sender', function () {
    config()->set('omni-core.service', 'omnisales');

    expect(fn () => serialize(welcomeJob()))
        ->toThrow(LogicException::class, 'may not be sent by [omnisales]. Allowed senders: omniauth.');
});

it('refuses to be sent when the service is not configured', function () {
    config()->set('omni-core.service', null);

    expect(fn () => serialize(welcomeJob()))->toThrow(LogicException::class, 'no omni-core.service set');
});

it('restores readonly properties and keeps them out of the queue in plain text', function () {
    config()->set('omni-core.service', 'omniauth');
    $payload = serialize(welcomeJob());
    $restored = unserialize($payload);

    expect($payload)->not->toContain('secret-pw')
        ->and($restored->temporaryPassword)->toBe('secret-pw')
        ->and($restored->statusKey())->toBe('mail:dispatch:d-1');
});

it('rejects a job encrypted with a key that is not one of its senders', function () {
    config()->set('omni-core.service', 'omniauth');
    $forged = str_replace(serialize('omniauth-omnimail'), serialize('omnidesk-omnimail'), serialize(welcomeJob()));

    expect(fn () => unserialize($forged))->toThrow(Exception::class, 'does not accept queue key [omnidesk-omnimail]');
});

it('hands the job to the handler registered in this service', function () {
    config()->set('omni-core.handlers', [SendWelcomeEmailJob::class => RecordingWelcomeHandler::class]);

    $job = welcomeJob();
    app()->call([$job, 'handle']);

    expect(RecordingWelcomeHandler::$handled)->toBe($job);
});

it('fails loudly when this service has no handler for the job', function () {
    config()->set('omni-core.handlers', []);

    expect(fn () => app()->call([welcomeJob(), 'handle']))
        ->toThrow(LogicException::class, 'has no handler in this service');
});

it('unserializes a payload queued under an old class name through an alias', function () {
    config()->set('omni-core.service', 'omnidesk');
    class_alias(RunLocalPromptJob::class, 'App\Jobs\RunLocalPromptJob');

    $new = serialize(new RunLocalPromptJob(dispatchId: 'd-2', prompt: 'Hello'));
    // What a not-yet-updated sender queues: the same payload under its own App\Jobs class name.
    $old = preg_replace('/^O:\d+:"'.preg_quote(RunLocalPromptJob::class, '/').'"/', 'O:'.strlen('App\Jobs\RunLocalPromptJob').':"App\Jobs\RunLocalPromptJob"', $new);

    expect(unserialize($old))->toBeInstanceOf(RunLocalPromptJob::class)
        ->and(unserialize($old)->prompt)->toBe('Hello');
});

it('gives FetchMailsJob a dispatch id when the sender did not pass one', function () {
    expect((new FetchMailsJob)->dispatchId)->not->toBeEmpty()
        ->and((new FetchMailsJob('x'))->statusKey())->toBe('mail:fetch:x');
});

it('writes the dispatch status hash with an expiry', function () {
    Redis::shouldReceive('hmset')->once()->with('ai:dispatch:d-1', Mockery::on(fn (array $f) => $f['status'] === 'completed' && $f['response'] === 'ok' && ! isset($f['error'])));
    Redis::shouldReceive('expire')->once()->with('ai:dispatch:d-1', 600);

    DispatchStatus::mark('ai:dispatch:d-1', 'completed', extra: ['response' => 'ok'], ttl: 600);
});
