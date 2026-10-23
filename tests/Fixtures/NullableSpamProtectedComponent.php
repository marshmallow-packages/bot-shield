<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Tests\Fixtures;

use Livewire\Component;
use Marshmallow\BotShield\Concerns\ProtectsAgainstSpam;
use Spatie\Honeypot\Http\Livewire\Concerns\HoneypotData;

/**
 * Declares the honeypot data nullable, as a component that assigns it in
 * mount() rather than at declaration commonly does.
 */
class NullableSpamProtectedComponent extends Component
{
    use ProtectsAgainstSpam;

    public ?HoneypotData $honeypotData = null;

    public bool $submitted = false;

    public function mount(): void
    {
        $this->honeypotData = new HoneypotData;
    }

    public function submit(): void
    {
        $this->protectAgainstSpam();

        $this->submitted = true;
    }

    public function render(): string
    {
        return '<div>spam protected</div>';
    }
}
