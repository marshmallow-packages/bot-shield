<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Tests\Fixtures;

use Livewire\Component;
use Marshmallow\BotShield\Livewire\RateLimitsSubmissions;

class ThrottledComponent extends Component
{
    public static int $runs = 0;

    public static function resetRuns(): void
    {
        self::$runs = 0;
    }

    #[RateLimitsSubmissions(attempts: 2, seconds: 60, form: 'throttled-test')]
    public function submit(): void
    {
        self::$runs++;
    }

    public function render(): string
    {
        return '<div>throttled</div>';
    }
}
