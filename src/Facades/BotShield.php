<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Marshmallow\BotShield\BotShield
 */
class BotShield extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Marshmallow\BotShield\BotShield::class;
    }
}
