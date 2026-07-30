<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Tests\Fixtures;

use Livewire\Component;
use Marshmallow\BotShield\Concerns\ProtectsAgainstBots;

class ProtectedComponent extends Component
{
    use ProtectsAgainstBots;

    public bool $submitted = false;

    public function submit(): void
    {
        $this->protectAgainstBots();

        $this->submitted = true;
    }

    public function render(): string
    {
        return '<div>protected</div>';
    }
}
