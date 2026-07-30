<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Captcha\Drivers;

use Illuminate\Http\Request;
use Marshmallow\BotShield\Captcha\CaptchaVerdict;
use Marshmallow\BotShield\Contracts\CaptchaDriver;

/**
 * For sites that protect forms with the honeypot and detector alone. Every
 * submission is skipped rather than passed, so monitoring can tell the
 * difference between "no captcha here" and "captcha accepted this".
 */
final class NullDriver implements CaptchaDriver
{
    public function verify(?string $token, Request $request, ?float $threshold = null): CaptchaVerdict
    {
        return CaptchaVerdict::skipped();
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'null';
    }

    public function siteKey(): ?string
    {
        return null;
    }
}
