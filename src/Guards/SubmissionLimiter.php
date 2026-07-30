<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Guards;

use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Marshmallow\BotShield\Contracts\RecordsEvents;
use Marshmallow\BotShield\Enums\EventType;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Per address submission throttling. A bot that gets past every other check
 * still cannot post the same form fifty times a minute.
 */
final class SubmissionLimiter
{
    public function __construct(
        private readonly Repository $config,
        private readonly RateLimiter $limiter,
        private readonly RecordsEvents $recorder,
    ) {}

    public function hit(Request $request, string $form, ?int $attempts = null, ?int $decaySeconds = null): void
    {
        if (! $this->enabled()) {
            return;
        }

        $attempts ??= (int) $this->config->get('bot-shield.rate_limit.attempts', 5);
        $decaySeconds ??= (int) $this->config->get('bot-shield.rate_limit.decay_seconds', 60);

        $key = $this->key($request, $form);

        if ($this->limiter->tooManyAttempts($key, max($attempts, 1))) {
            $this->recorder->record(EventType::DetectorBlock, 'rate-limited', ['form' => $form]);

            throw new TooManyRequestsHttpException(
                $this->limiter->availableIn($key),
                (string) trans('bot-shield::messages.too_many_submissions'),
            );
        }

        $this->limiter->hit($key, max($decaySeconds, 1));
    }

    public function clear(Request $request, string $form): void
    {
        $this->limiter->clear($this->key($request, $form));
    }

    public function key(Request $request, string $form): string
    {
        return 'bot-shield:'.hash('sha256', $form.'|'.(string) $request->ip());
    }

    public function enabled(): bool
    {
        if (! $this->truthy('bot-shield.enabled')) {
            return false;
        }

        return $this->truthy('bot-shield.rate_limit.enabled');
    }

    private function truthy(string $configKey): bool
    {
        return filter_var(
            $this->config->get($configKey, false),
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE,
        ) === true;
    }
}
