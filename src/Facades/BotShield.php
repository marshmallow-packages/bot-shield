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
     *
     * The fake answers as whichever captcha driver the application configured,
     * so rendered markup is the markup the site ships. Name a driver to override
     * that, which is only needed when the test is about a driver the
     * application does not use.
     */
    public static function fake(?string $driver = null): BotShieldFake
    {
        $fake = static::$app->make(BotShieldFake::class)->activate($driver);

        static::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return BotShieldManager::class;
    }
}
