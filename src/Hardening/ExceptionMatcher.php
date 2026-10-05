<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Hardening;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Throwable;

final class ExceptionMatcher
{
    public function __construct(
        private readonly Repository $config,
    ) {}

    public function matchesBotNoise(Throwable $exception, ?Request $request = null): bool
    {
        return $this->matchesAny($exception, 'bot-shield.exceptions.rules', $request);
    }

    /**
     * Bot noise that needs no detector verdict, see ExceptionRule::$always.
     */
    public function matchesUnconditionalBotNoise(Throwable $exception, ?Request $request = null): bool
    {
        foreach ($this->rules('bot-shield.exceptions.rules') as $rule) {
            if ($rule->always && $rule->matches($exception, $request)) {
                return true;
            }
        }

        return false;
    }

    public function matchesTransientError(Throwable $exception): bool
    {
        return $this->matchesAny($exception, 'bot-shield.exceptions.transient_errors.rules');
    }

    private function matchesAny(Throwable $exception, string $configKey, ?Request $request = null): bool
    {
        foreach ($this->rules($configKey) as $rule) {
            if ($rule->matches($exception, $request)) {
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
