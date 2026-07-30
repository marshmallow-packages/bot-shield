<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Contracts;

use Illuminate\Http\Request;

interface BotDetector
{
    public function isBot(Request $request): bool;
}
