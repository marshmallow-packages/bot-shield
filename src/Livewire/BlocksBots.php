<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Livewire;

use Attribute;
use Closure;
use Marshmallow\BotShield\Guards\FormGuard;

/**
 * Refuses a guarded Livewire action before it runs:
 *
 *     #[BlocksBots]
 *     public function submit(): void
 *
 * Not calling $returnEarly lets the action proceed, so a request that survives
 * the guard runs exactly as it would without the attribute.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class BlocksBots extends BotShieldAttribute
{
    /**
     * @param  array<array-key, mixed>  $params
     */
    public function call(array $params, Closure $returnEarly): void
    {
        $guard = $this->resolve(FormGuard::class);

        if (! $guard instanceof FormGuard) {
            return;
        }

        $guard->guard($this->currentRequest());
    }
}
