<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Tests;

use Illuminate\Foundation\Application;
use Livewire\LivewireServiceProvider;
use Marshmallow\BotShield\BotShieldServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            BotShieldServiceProvider::class,
        ];
    }

    /**
     * Livewire signs component snapshots, so the test application needs a key.
     */
    protected function defineEnvironment($app): void
    {
        /** @var Application $app */
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }
}
