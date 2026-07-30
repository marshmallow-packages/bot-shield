<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Concerns;

use Illuminate\Container\Container;
use Illuminate\Foundation\Exceptions\Handler;
use Marshmallow\BotShield\Hardening\ExceptionHardening;
use RuntimeException;

/**
 * Wires Bot Shield into an application that still has its own
 * App\Exceptions\Handler bound as the exception handler. Call
 * hardenAgainstBots() from the handler's register() method.
 *
 * Applications configuring exceptions in bootstrap/app.php should call
 * BotShield::handles($exceptions) inside withExceptions() instead.
 */
trait HardensAgainstBots
{
    public function hardenAgainstBots(): void
    {
        if (! $this instanceof Handler) {
            throw new RuntimeException(sprintf(
                'The [%s] trait may only be used on a class extending [%s].',
                HardensAgainstBots::class,
                Handler::class,
            ));
        }

        Container::getInstance()->make(ExceptionHardening::class)->register($this);
    }
}
