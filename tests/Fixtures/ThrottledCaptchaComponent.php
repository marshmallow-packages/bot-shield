<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Tests\Fixtures;

use Livewire\Component;
use Marshmallow\BotShield\Livewire\RateLimitsSubmissions;
use Marshmallow\BotShield\Livewire\ValidatesRecaptcha;

class ThrottledCaptchaComponent extends Component
{
    public static int $runs = 0;

    public string $gRecaptchaResponse = '';

    public static function resetRuns(): void
    {
        self::$runs = 0;
    }

    /** Limiter declared first, so its count must still wait for the captcha verdict. */
    #[RateLimitsSubmissions(attempts: 2, seconds: 60, form: 'throttled-captcha-test')]
    #[ValidatesRecaptcha]
    public function submit(): void
    {
        self::$runs++;
    }

    public function render(): string
    {
        return '<div>throttled captcha</div>';
    }
}
