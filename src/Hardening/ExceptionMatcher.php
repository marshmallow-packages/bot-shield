<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Hardening;

use Illuminate\Contracts\Config\Repository;
use Throwable;

final class ExceptionMatcher
{
    public function __construct(
        private readonly Repository $config,
    ) {}

    public function matchesBotNoise(Throwable $exception): bool
    {
        return $this->matchesAny($exception, 'bot-shield.exceptions.rules');
    }

    public function matchesTransientError(Throwable $exception): bool
    {
        return $this->matchesAny($exception, 'bot-shield.exceptions.transient_errors.rules');
    }

    private function matchesAny(Throwable $exception, string $configKey): bool
    {
        foreach ($this->rules($configKey) as $rule) {
            if ($rule->matches($exception)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<ExceptionRule>
     */
    private function rules(string $configKey): array
    {
        $rules = [];

        foreach ((array) $this->config->get($configKey, []) as $configured) {
            if (! is_array($configured)) {
                continue;
            }

            $rules[] = ExceptionRule::fromArray($configured);
        }

        return $rules;
    }
}
