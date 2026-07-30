<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Tests\Fixtures;

use Illuminate\Http\Request;
use Marshmallow\BotShield\Contracts\BotDetector;

class AlwaysBotDetector implements BotDetector
{
    public function isBot(Request $request): bool
    {
        return true;
    }
}
