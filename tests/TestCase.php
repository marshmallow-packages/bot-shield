<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Tests;

use Marshmallow\BotShield\BotShieldServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            BotShieldServiceProvider::class,
        ];
    }
}
