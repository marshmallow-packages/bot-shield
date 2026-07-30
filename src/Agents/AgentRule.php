<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Agents;

use Marshmallow\BotShield\Enums\AgentAction;

/**
 * One user agent rule: a case insensitive substring, or a regular expression
 * when "regex" is true, paired with the action to take.
 */
final readonly class AgentRule
{
    public function __construct(
        public string $pattern,
        public AgentAction $action,
        public bool $regex = false,
    ) {}

    /**
     * Rules come from a user editable config file, so a malformed entry is
     * discarded rather than allowed to break request handling.
     *
     * @param  array<mixed, mixed>  $rule
     */
    public static function fromArray(array $rule): ?self
    {
        $pattern = $rule['pattern'] ?? null;
        $action = AgentAction::tryFromValue($rule['action'] ?? null);

        if (! is_string($pattern)) {
            return null;
        }

        if ($pattern === '') {
            return null;
        }

        if (! $action instanceof AgentAction) {
            return null;
        }

        return new self(
            pattern: $pattern,
            action: $action,
            regex: (bool) ($rule['regex'] ?? false),
        );
    }

    public function matches(string $userAgent): bool
    {
        if (! $this->regex) {
            return str_contains(strtolower($userAgent), strtolower($this->pattern));
        }

        return @preg_match($this->pattern, $userAgent) === 1;
    }
}
