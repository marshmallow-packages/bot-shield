<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Captcha\Drivers;

use Marshmallow\BotShield\Captcha\CaptchaVerdict;
use Marshmallow\BotShield\Enums\CaptchaOutcome;

/**
 * Challenge based reCAPTCHA, as a checkbox or invisible badge. The response
 * carries no score, so a successful verification is simply a pass.
 */
final class GoogleV2Driver extends GoogleDriver
{
    public function name(): string
    {
        return 'google-v2';
    }

    protected function verdictFrom(array $payload, ?float $threshold): CaptchaVerdict
    {
        if (! $this->succeeded($payload)) {
            return new CaptchaVerdict(
                CaptchaOutcome::Failed,
                errorCodes: $this->errorCodes($payload),
            );
        }

        return CaptchaVerdict::passed();
    }
}
