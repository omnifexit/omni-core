<?php

namespace Omnifexit\OmniCore\Tests;

use Omnifexit\OmniCore\OmniCoreServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [OmniCoreServiceProvider::class];
    }
}
