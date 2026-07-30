<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Tests\Fixtures;

use Livewire\Component;
use Marshmallow\BotShield\Concerns\ProtectsAgainstSpam;

/**
 * Uses the trait but forgets the HoneypotData property.
 */
class UnprotectedSpamComponent extends Component
{
    use ProtectsAgainstSpam;

    public function submit(): void
    {
        $this->protectAgainstSpam();
    }

    public function render(): string
    {
        return '<div>unprotected</div>';
    }
}
