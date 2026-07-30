<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Livewire;

use Attribute;
use Closure;
use Marshmallow\BotShield\Guards\SubmissionLimiter;

/**
 * Throttles a Livewire action per address:
 *
 *     #[RateLimitsSubmissions(attempts: 3, seconds: 60)]
 *     public function submit(): void
 *
 * Omit the arguments to use the configured defaults.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class RateLimitsSubmissions extends BotShieldAttribute
{
    public function __construct(
        private readonly ?int $attempts = null,
        private readonly ?int $seconds = null,
        private readonly ?string $form = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $params
     */
    public function call(array $params, Closure $returnEarly): void
    {
        $limiter = $this->resolve(SubmissionLimiter::class);

        if (! $limiter instanceof SubmissionLimiter) {
            return;
        }

        $limiter->hit(
            $this->currentRequest(),
            $this->form ?? $this->componentName().':'.$this->actionName(),
            $this->attempts,
            $this->seconds,
        );
    }
}
