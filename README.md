# omni-core

Code shared by the OmnifexIT services, so a fix lands everywhere at once instead of in every copy.

| What | Class |
|---|---|
| Encrypted queue jobs between services (pairwise keys, forged keys rejected) | `Omnifexit\OmniCore\Queue\EncryptsWithQueueKey` |
| Sentinel driver that trusts Tailscale's 100.64.0.0/10 range (registered automatically when Horizon/Sentinel is installed) | `Omnifexit\OmniCore\Sentinel\TailscaleAwareSentinelDriver` |
| The jobs services send each other, one class for sender and receiver | `Omnifexit\OmniCore\Jobs\{Mail,Desk,Ai,Message}\*` (base: `Jobs\ServiceJob`) |
| User and role sync between OmniAuth and its client apps | `Omnifexit\OmniCore\Jobs\Auth\*` (base: `ClientAppJob`) |
| OmniAuth SSO login, app launcher, user-revocation middleware, and sync handlers for client apps | `Omnifexit\OmniCore\Sso\*` |
| Checking a service is wired up correctly (handlers, Horizon queues, no local copies) | `Omnifexit\OmniCore\Testing\ServiceContract::problems()` |
| Writing a job's progress for the sender to poll | `Omnifexit\OmniCore\Queue\DispatchStatus::mark($job->statusKey(), ...)` |

This repo is public on purpose: it holds no secrets. The queue keys live in each service's `.env`.

## Installing in a service

Add the repository and the package to the service's `composer.json`:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/omnifexit/omni-core.git", "no-api": true }
],
"require": {
    "omnifexit/omni-core": "^1.0"
}
```

`no-api` makes Composer clone over git instead of calling the GitHub API, so CI and the Docker builds never hit
GitHub's rate limit for anonymous API requests.

Then:
- Replace `use App\Support\EncryptsWithQueueKey;` with `use Omnifexit\OmniCore\Queue\EncryptsWithQueueKey;`.
- Delete the service's own `app/Support/EncryptsWithQueueKey.php` and `TailscaleAwareSentinelDriver.php`.
- Remove the `Sentinel::extend(...)` line from its `AppServiceProvider`. The package's service provider registers the
  driver itself.

## Jobs between services

Every job one service sends another lives here, in `src/Jobs/<receiver>/`. It extends `ServiceJob` and declares its
properties and `senderKeys()`: which service may send it, and with which pairwise key. The class is identical on
both sides, so sender and receiver can't drift apart.

- **Every service** publishes `config/omni-core.php` and sets `service` (e.g. `omnidesk`), so a job knows which key
  to encrypt with.
- **The receiving service** registers a handler for each job it receives, in `handlers`
  (`SendWelcomeEmailJob::class => App\JobHandlers\SendWelcomeEmail::class`). The handler's
  `handle(SendWelcomeEmailJob $job)` does the work. Dependencies are injected, and `$job->attempts()` and
  `$job->fail()` work as usual. An optional `failed(SendWelcomeEmailJob $job, ?Throwable $exception)` runs once
  after the last attempt failed.
- **A sending service** just dispatches the job, with no handler. Each job knows its queue name:
  `->onQueue(SendWelcomeEmailJob::queueName())`. For the sync jobs the app matters: `SyncRoleChangeJob::queueName('omnidesk')`.
- **Every service** has a contract test that runs `ServiceContract::problems()`. It checks that every handler exists
  and accepts its job, that Horizon listens on each handled job's queue, and that `app/Jobs` holds no copy of a
  shared job:

  ```php
  it('is wired up correctly for omni-core\'s shared jobs', function () {
      expect(ServiceContract::problems())->toBe([]);
  });
  ```

### Jobs between OmniAuth and its client apps

`Jobs\Auth\*` extend `ClientAppJob` instead of declaring `senderKeys()`:
- **Keys:** an app always uses its own key, `omniauth-{service}`. OmniAuth uses the key of the client the job is
  about (`$clientId`), through its `ClientQueueKeys` binding.
- **Safety check:** OmniAuth discards a job about one client that arrived on another client's key.
- **Direction:** `sentByOmniAuth()` / `sentByClientApps()` say who may send each job.

### Adding a job

1. Add the class under `src/Jobs/<Receiver>/`, with its `senderKeys()`, its `queueName()` and, if the sender polls for
   the result, a `statusKey()`. Add a test.
2. Release a minor version.
3. Update the receiver first: register the handler, deploy.
4. Then update the sender: dispatch the job.

### Changing a job's properties

Senders and receivers update at different moments, so a change must work for messages queued by the old version:
- **Adding a property:** give it a default.
- **Removing or renaming one:** first make the receiver handle both, then change the sender, then clean up.

### Moving an existing job here

The class name is part of the queued message, so old and new services must understand each other during the move.
- **One-way job** (one service sends, another receives): keep `class_alias(New::class, 'App\Jobs\Old')` in the
  receiver until every sender dispatches the new class.
- **A service both sends and receives it:** replace its old class with an empty `class Old extends New {}`. It keeps
  sending under the old name while it already accepts the new one; the handler registered for `New` handles both.
  Switch to `New` once every service has that subclass, then delete it.

## Using the job encryption directly

```php
class SendInvoiceEmailJob implements ShouldQueue
{
    use Dispatchable, EncryptsWithQueueKey, InteractsWithQueue, Queueable;

    public function __construct(public readonly string $email) {}

    public function getEncryptionKey(): string
    {
        return 'omnibilling-omnimail'; // config('queue.keys.omnibilling-omnimail') in both services
    }

    // Only when several services send this job:
    public function acceptedEncryptionKeys(): array
    {
        return ['omnibilling-omnimail', 'omnidesk-omnimail'];
    }
}
```

## Changing the package

1. Make the change with tests (`vendor/bin/pest`, `vendor/bin/pint`). CI runs both against Laravel 12 and 13.
2. Merge to `main`, then tag a release: `git tag v1.x.y && git push --tags`. Use a patch version for fixes, minor
   for new classes, and major for anything a service has to change for.
3. Dependabot opens the update in every service, and minor/patch updates merge themselves once the service's CI
   passes.

To try a change in a service before releasing it, point the service at your local checkout temporarily:
`composer config repositories.omni-core path ../../OmniCore`, then `composer update omnifexit/omni-core`. Don't
commit that.
