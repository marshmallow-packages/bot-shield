<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Tests\Fixtures;

use Livewire\Component;
use Marshmallow\BotShield\Livewire\ValidatesRecaptcha;

class CaptchaComponent extends Component
{
    public static int $runs = 0;

    public string $gRecaptchaResponse = '';

    public bool $submitted = false;

    public static function resetRuns(): void
    {
        self::$runs = 0;
    }

    #[ValidatesRecaptcha]
    public function submit(): void
    {
        self::$runs++;

        $this->submitted = true;
    }

    public function render(): string
    {
        return '<div>captcha</div>';
    }
}
