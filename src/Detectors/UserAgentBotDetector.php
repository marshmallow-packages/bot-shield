<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Detectors;

use Illuminate\Http\Request;
use Marshmallow\BotShield\Contracts\BotDetector;

/**
 * Treats any request whose user agent does not advertise "Mozilla/" as a bot.
 *
 * Every mainstream browser sends it and scripted clients such as curl,
 * python-requests and Go's http package do not, which makes this a cheap first
 * pass. It is deliberately permissive: a bot that spoofs a browser user agent
 * passes, and is caught by the agent block rules instead.
 */
final class UserAgentBotDetector implements BotDetector
{
    public function isBot(Request $request): bool
    {
        $userAgent = $request->userAgent();

        if (blank($userAgent)) {
            return true;
        }

        return ! str_contains($userAgent, 'Mozilla/');
    }
}
