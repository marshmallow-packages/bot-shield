<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Tests\Fixtures;

use Illuminate\Foundation\Exceptions\Handler;
use Marshmallow\BotShield\Concerns\HardensAgainstBots;

/**
 * Stands in for an application that still binds its own App\Exceptions\Handler.
 */
class LegacyHandlerStub extends Handler
{
    use HardensAgainstBots;
}
