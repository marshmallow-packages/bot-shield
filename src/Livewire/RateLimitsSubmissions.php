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
 * Omit the arguments to use the configured defaults. Every submit counts,
 * including one refused for a spent captcha token: a replayed token is the
 * cheapest flood there is, and a double click costs a human one attempt.
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
    public function call(array $params, Closure $returnEarly): ?Closure
    {
        $limiter = $this->resolve(SubmissionLimiter::class);

        if (! $limiter instanceof SubmissionLimiter) {
            return null;
        }

        $request = $this->currentRequest();
        $form = $this->form ?? $this->componentName().':'.$this->actionName();

        $limiter->ensureAllowed($request, $form, $this->attempts);

        // Livewire runs this after every attribute hook and the action, so a
        // submit the captcha refused is counted whichever order the attributes
        // are declared in.
        return function () use ($limiter, $request, $form): void {
            $limiter->count($request, $form, $this->seconds);
        };
    }
}
