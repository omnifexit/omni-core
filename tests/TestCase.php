<?php

namespace Omnifexit\OmniCore\Tests;

use Laravel\Socialite\SocialiteServiceProvider;
use Omnifexit\OmniCore\OmniCoreServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            SocialiteServiceProvider::class,
            PermissionServiceProvider::class,
            LaravelDataServiceProvider::class,
            OmniCoreServiceProvider::class,
        ];
    }
}
