<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Omnifexit\OmniCore\Jobs\Message\SendDiscordMessageJob;

function failedJobNamed(string $name): Job
{
    $job = Mockery::mock(Job::class);
    $job->shouldReceive('resolveName')->andReturn($name);
    $job->shouldReceive('getQueue')->andReturn('some-queue');

    return $job;
}

beforeEach(function () {
    Http::fake();
    Cache::flush();

    config()->set('omni-core.service', 'omnimail');
    config()->set('omni-core.alerts.enabled', true);
    config()->set('omni-core.alerts.webhooks', [
        'errors' => 'https://discord.test/errors',
        'failed-jobs' => 'https://discord.test/failed-jobs',
    ]);
});

it('posts a reported exception to the errors webhook', function () {
    report(new RuntimeException('Something broke'));

    Http::assertSent(fn ($request) => $request->url() === 'https://discord.test/errors'
        && str_contains($request['content'], 'omnimail')
        && str_contains($request['content'], 'RuntimeException')
        && str_contains($request['content'], 'Something broke'));
});

it('sends nothing when alerts are off', function () {
    config()->set('omni-core.alerts.enabled', false);

    report(new RuntimeException('Something broke'));

    Http::assertNothingSent();
});

it('posts the same error once per window, and a different error separately', function () {
    $broken = fn (string $message) => new RuntimeException($message);

    report($broken('one'));
    report($broken('one'));
    report($broken('two'));

    Http::assertSentCount(2);
});

it('does not post what Laravel does not report, like an unauthenticated request', function () {
    report(new AuthenticationException);

    Http::assertNothingSent();
});

it('leaves a job attempt to the failed-jobs channel instead of posting it as an error', function () {
    $exception = new RuntimeException('Attempt failed');

    event(new JobExceptionOccurred('redis', failedJobNamed('App\Jobs\SomeJob'), $exception));
    report($exception);

    Http::assertNothingSent();
});

it('posts a job that failed for good to the failed-jobs webhook', function () {
    event(new JobFailed('redis', failedJobNamed('App\Jobs\SomeJob'), new RuntimeException('Out of tries')));

    Http::assertSent(fn ($request) => $request->url() === 'https://discord.test/failed-jobs'
        && str_contains($request['content'], 'App\Jobs\SomeJob')
        && str_contains($request['content'], 'some-queue')
        && str_contains($request['content'], 'Out of tries'));
});

it('goes through the queue when this service can send to OmniMessage', function () {
    Queue::fake();
    config()->set('omni-core.service', 'omnidesk');
    config()->set('queue.connections.message_service', ['driver' => 'redis']);

    report(new RuntimeException('Something broke'));

    Queue::assertPushedOn(SendDiscordMessageJob::queueName(), SendDiscordMessageJob::class, fn ($job) => $job->channel === 'errors'
        && str_contains($job->text, 'Something broke'));
    Http::assertNothingSent();
});

it('falls back to the webhook when the queue is down', function () {
    Queue::shouldReceive('connection')->andThrow(new RuntimeException('Redis is down'));
    config()->set('omni-core.service', 'omnidesk');
    config()->set('queue.connections.message_service', ['driver' => 'redis']);

    report(new RuntimeException('Something broke'));

    Http::assertSent(fn ($request) => $request->url() === 'https://discord.test/errors');
});

it('reports a failed Discord job by webhook, so a broken queue path cannot loop', function () {
    Queue::fake();
    config()->set('omni-core.service', 'omnidesk');
    config()->set('queue.connections.message_service', ['driver' => 'redis']);

    event(new JobFailed('redis', failedJobNamed(SendDiscordMessageJob::class), new RuntimeException('Discord said no')));

    Queue::assertNothingPushed();
    Http::assertSent(fn ($request) => $request->url() === 'https://discord.test/failed-jobs');
});

it('never throws or loops when the alert itself cannot be sent', function () {
    Http::fake(fn () => throw new RuntimeException('Discord is down'));

    expect(fn () => report(new RuntimeException('Something broke')))->not->toThrow(Throwable::class);

    Http::assertSentCount(0);
});

it('still alerts when the cache is down', function () {
    Cache::shouldReceive('store')->andThrow(new RuntimeException('Redis is down'));
    Cache::shouldReceive('add')->andThrow(new RuntimeException('Redis is down'));

    report(new RuntimeException('Something broke'));

    Http::assertSent(fn ($request) => $request->url() === 'https://discord.test/errors');
});

it('cannot ping everyone from an exception message, and leaves the query string out', function () {
    $this->app->instance('request', Request::create('https://desk.test/api/tickets?token=secret', 'POST'));

    report(new RuntimeException('Hello @everyone'));

    Http::assertSent(fn ($request) => ! str_contains($request['content'], '@everyone')
        && $request['allowed_mentions'] === ['parse' => []]
        && ! str_contains($request['content'], 'secret'));
});

it('does nothing without a webhook when there is no queue path', function () {
    config()->set('omni-core.alerts.webhooks.errors', null);

    report(new RuntimeException('Something broke'));

    Http::assertNothingSent();
});

class AlertsTestFailingJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function handle(): void
    {
        throw new RuntimeException('The job broke');
    }
}

it('stays quiet on a retry and posts once the job failed for good, with a real worker', function () {
    config()->set('queue.default', 'database');
    config()->set('queue.connections.database', ['driver' => 'database', 'table' => 'jobs', 'queue' => 'default', 'retry_after' => 90]);
    config()->set('queue.failed.driver', 'null');
    Schema::create('jobs', function (Blueprint $table) {
        $table->id();
        $table->string('queue');
        $table->longText('payload');
        $table->unsignedTinyInteger('attempts');
        $table->unsignedInteger('reserved_at')->nullable();
        $table->unsignedInteger('available_at');
        $table->unsignedInteger('created_at');
    });

    dispatch(new AlertsTestFailingJob);

    $this->artisan('queue:work', ['connection' => 'database', '--once' => true, '--backoff' => 0]);
    Http::assertNothingSent();

    $this->artisan('queue:work', ['connection' => 'database', '--once' => true, '--backoff' => 0]);
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request->url() === 'https://discord.test/failed-jobs'
        && str_contains($request['content'], 'AlertsTestFailingJob')
        && str_contains($request['content'], 'The job broke'));
});
