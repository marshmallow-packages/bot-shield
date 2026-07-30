<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Concerns;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Marshmallow\BotShield\Guards\FormGuard;

/**
 * Fallback for components that cannot use the #[BlocksBots] attribute. Call
 * protectAgainstBots() at the top of a submit action.
 */
trait ProtectsAgainstBots
{
    protected function protectAgainstBots(): void
    {
        $container = Container::getInstance();

        $container->make(FormGuard::class)->guard($container->make(Request::class));
    }
}
