<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Agents;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Marshmallow\BotShield\Enums\AgentAction;

/**
 * The configured agent rules, evaluated in order. The first rule whose pattern
 * matches the user agent wins, so put your most specific rules first.
 */
final class AgentRules
{
    public function __construct(
        private readonly Repository $config,
    ) {}

    public function actionFor(Request $request): ?AgentAction
    {
        $userAgent = $request->userAgent();

        if (blank($userAgent)) {
            return null;
        }

        return $this->actionForUserAgent($userAgent);
    }

    public function actionForUserAgent(string $userAgent): ?AgentAction
    {
        foreach ($this->all() as $rule) {
            if ($rule->matches($userAgent)) {
                return $rule->action;
            }
        }

        return null;
    }

    /**
     * @return list<AgentRule>
     */
    public function all(): array
    {
        $rules = [];

        foreach ((array) $this->config->get('bot-shield.agents.rules', []) as $configured) {
            if (! is_array($configured)) {
                continue;
            }

            $rule = AgentRule::fromArray($configured);

            if (! $rule instanceof AgentRule) {
                continue;
            }

            $rules[] = $rule;
        }

        return $rules;
    }

    /**
     * @return list<AgentRule>
     */
    public function matching(AgentAction $action): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn (AgentRule $rule): bool => $rule->action === $action,
        ));
    }
}
