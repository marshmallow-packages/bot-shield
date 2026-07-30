<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Concerns;

use Illuminate\Container\Container;
use Marshmallow\BotShield\Guards\SpamGuard;

/**
 * Honeypot protection for a Livewire component:
 *
 *     use ProtectsAgainstSpam;
 *
 *     public HoneypotData $honeypotData;
 *
 *     public function submit(): void
 *     {
 *         $this->protectAgainstSpam();
 *         // ...
 *     }
 *
 * Pair it with <x-bot-shield::honeypot livewire-model="honeypotData" /> in the
 * component's view.
 */
trait ProtectsAgainstSpam
{
    protected function protectAgainstSpam(): void
    {
        Container::getInstance()->make(SpamGuard::class)->guard($this);
    }
}
