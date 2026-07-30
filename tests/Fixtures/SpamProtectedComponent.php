<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Tests\Fixtures;

use Livewire\Component;
use Marshmallow\BotShield\Concerns\ProtectsAgainstSpam;
use Spatie\Honeypot\Http\Livewire\Concerns\HoneypotData;

class SpamProtectedComponent extends Component
{
    use ProtectsAgainstSpam;

    public static int $runs = 0;

    public HoneypotData $honeypotData;

    public bool $submitted = false;

    public static function resetRuns(): void
    {
        self::$runs = 0;
    }

    public function mount(): void
    {
        $this->honeypotData = new HoneypotData;
    }

    public function submit(): void
    {
        $this->protectAgainstSpam();

        self::$runs++;

        $this->submitted = true;
    }

    public function render(): string
    {
        return '<div>spam protected</div>';
    }
}
