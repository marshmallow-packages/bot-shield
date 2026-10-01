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
 * Omit the arguments to use the configured defaults. A submit refused only
 * because it resent a spent captcha token is not counted: that is a stale
 * page, not a flood.
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

        // Livewire runs this after every attribute hook and the action, so the
        // captcha verdict is known whichever order the attributes are declared in.
        return function () use ($limiter, $request, $form): void {
            if ($request->attributes->getBoolean(ValidatesRecaptcha::SPENT_TOKEN)) {
                return;
            }

            $limiter->count($request, $form, $this->seconds);
        };
    }
}
