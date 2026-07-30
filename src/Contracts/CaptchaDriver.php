<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Contracts;

use Illuminate\Http\Request;
use Marshmallow\BotShield\Captcha\CaptchaVerdict;

interface CaptchaDriver
{
    /**
     * Verify a submitted captcha token.
     *
     * @param  float|null  $threshold  Minimum score to accept, for drivers that
     *                                 score requests. Null uses the driver's
     *                                 configured default.
     */
    public function verify(?string $token, Request $request, ?float $threshold = null): CaptchaVerdict;

    /**
     * Whether the driver has everything it needs to verify. A driver that is
     * not configured is skipped rather than failing every submission.
     */
    public function isConfigured(): bool;

    public function name(): string;

    public function siteKey(): ?string;
}
