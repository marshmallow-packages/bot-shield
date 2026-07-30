<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Facades;

use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Marshmallow\BotShield\BotShield as BotShieldManager;
use Marshmallow\BotShield\Contracts\BotDetector;
use Marshmallow\BotShield\Testing\BotShieldFake;

/**
 * @method static void handles(Exceptions|Handler $exceptions)
 * @method static bool isBot(?Request $request = null)
 * @method static BotDetector detector()
 *
 * @see BotShieldManager
 */
class BotShield extends Facade
{
    /**
     * Script the captcha, collect events in memory, and enable the assertions.
     */
    public static function fake(): BotShieldFake
    {
        $fake = static::$app->make(BotShieldFake::class)->activate();

        static::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return BotShieldManager::class;
    }
}
