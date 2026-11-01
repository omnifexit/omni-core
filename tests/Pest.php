<?php

use Omnifexit\OmniCore\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

function queueKey(): string
{
    return 'base64:'.base64_encode(random_bytes(32));
}
