<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Tests\Fixtures;

use Livewire\Component;
use Marshmallow\BotShield\Livewire\BlocksBots;

/**
 * The run counters live outside component state on purpose: a blocked
 * submission never returns a Livewire payload, so component properties are not
 * readable afterwards and cannot tell us whether the action ran.
 */
class GuardedComponent extends Component
{
    public static int $guardedRuns = 0;

    public static int $unguardedRuns = 0;

    public bool $submitted = false;

    public static function resetRuns(): void
    {
        self::$guardedRuns = 0;
        self::$unguardedRuns = 0;
    }

    #[BlocksBots]
    public function submit(): void
    {
        self::$guardedRuns++;

        $this->submitted = true;
    }

    public function unguardedSubmit(): void
    {
        self::$unguardedRuns++;

        $this->submitted = true;
    }

    public function render(): string
    {
        return '<div>guarded</div>';
    }
}
