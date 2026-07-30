<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Detectors;

use Illuminate\Http\Request;
use Marshmallow\BotShield\Agents\AgentRules;
use Marshmallow\BotShield\Contracts\BotDetector;
use Marshmallow\BotShield\Enums\AgentAction;

/**
 * Applies the configured agent rules before consulting the underlying driver,
 * so an explicit rule beats any heuristic. This is what lets a bot spoofing a
 * browser user agent still be caught, and a monitoring service that looks like
 * a script still be trusted.
 */
final class RuleAwareBotDetector implements BotDetector
{
    public function __construct(
        private readonly AgentRules $rules,
        private readonly BotDetector $driver,
    ) {}

    public function isBot(Request $request): bool
    {
        $action = $this->rules->actionFor($request);

        if ($action instanceof AgentAction) {
            return $action->treatsRequestAsBot();
        }

        return $this->driver->isBot($request);
    }

    public function driver(): BotDetector
    {
        return $this->driver;
    }
}
