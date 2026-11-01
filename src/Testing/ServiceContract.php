<?php

namespace Omnifexit\OmniCore\Testing;

use Omnifexit\OmniCore\Jobs\ServiceJob;
use ReflectionMethod;
use ReflectionNamedType;
use Symfony\Component\Finder\Finder;

/**
 * Checks that a service is wired up correctly for omni-core's shared jobs. Each service runs it in a test:
 *
 *     expect(ServiceContract::problems())->toBe([]);
 *
 * Every problem is a sentence saying what is wrong and where to fix it.
 */
class ServiceContract
{
    /**
     * @return list<string>
     */
    public static function problems(): array
    {
        $service = config('omni-core.service');

        if (! is_string($service) || $service === '') {
            return ['config/omni-core.php has no service name (omni-core.service), so shared jobs can\'t pick their queue key.'];
        }

        return [
            ...self::handlerProblems(),
            ...self::queueProblems($service),
            ...self::localCopyProblems(),
        ];
    }

    /**
     * @return list<string>
     */
    private static function handlerProblems(): array
    {
        $problems = [];

        foreach (config('omni-core.handlers', []) as $job => $handler) {
            if (! is_subclass_of($job, ServiceJob::class)) {
                $problems[] = "omni-core.handlers lists [{$job}], which is not an omni-core shared job.";

                continue;
            }

            if (! class_exists($handler)) {
                $problems[] = "The handler [{$handler}] for {$job} doesn't exist.";

                continue;
            }

            if (! method_exists($handler, 'handle')) {
                $problems[] = "The handler [{$handler}] for {$job} has no handle() method.";

                continue;
            }

            $type = (new ReflectionMethod($handler, 'handle'))->getParameters()[0]?->getType();

            if (! $type instanceof ReflectionNamedType || ! is_a($job, $type->getName(), true)) {
                $problems[] = "{$handler}::handle() must take the job as its first argument ({$job} \$job).";
            }
        }

        return $problems;
    }

    /**
     * @return list<string>
     */
    private static function queueProblems(string $service): array
    {
        $listened = self::horizonQueues();
        $problems = [];

        foreach (array_keys(config('omni-core.handlers', [])) as $job) {
            if (! is_subclass_of($job, ServiceJob::class)) {
                continue;
            }

            $queue = $job::queueName($service);

            if (! in_array($queue, $listened, true)) {
                $problems[] = "{$job} arrives on the [{$queue}] queue, but no Horizon supervisor in config/horizon.php listens on it, so it would never run.";
            }
        }

        return $problems;
    }

    /**
     * Every queue any Horizon supervisor listens on, in any environment.
     *
     * @return list<string>
     */
    private static function horizonQueues(): array
    {
        $supervisors = [
            ...array_values(config('horizon.defaults', [])),
            ...collect(config('horizon.environments', []))->flatMap(fn (array $environment) => array_values($environment))->all(),
        ];

        return collect($supervisors)
            ->flatMap(fn (array $supervisor) => (array) ($supervisor['queue'] ?? []))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private static function localCopyProblems(): array
    {
        $jobsDirectory = app_path('Jobs');

        if (! is_dir($jobsDirectory)) {
            return [];
        }

        $shared = collect(Finder::create()->files()->in(__DIR__.'/../Jobs')->name('*.php'))
            ->map(fn ($file) => $file->getBasename('.php'))
            ->reject(fn (string $name) => in_array($name, ['ServiceJob', 'ClientAppJob'], true))
            ->values();

        return collect(Finder::create()->files()->in($jobsDirectory)->name('*.php'))
            ->map(fn ($file) => $file->getBasename('.php'))
            ->intersect($shared)
            ->map(fn (string $name) => "app/Jobs/{$name}.php copies a job that lives in omni-core; use Omnifexit\\OmniCore\\Jobs\\...\\{$name} instead.")
            ->values()
            ->all();
    }
}
