# omni-core

Code shared by the OmnifexIT services, so a fix lands everywhere at once instead of in every copy.

| What | Class |
|---|---|
| Encrypted queue jobs between services (pairwise keys, forged keys rejected) | `Omnifexit\OmniCore\Queue\EncryptsWithQueueKey` |
| Sentinel driver that trusts Tailscale's 100.64.0.0/10 range (registered automatically when Horizon/Sentinel is installed) | `Omnifexit\OmniCore\Sentinel\TailscaleAwareSentinelDriver` |

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

## Using the job encryption

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
