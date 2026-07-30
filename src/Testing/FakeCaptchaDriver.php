<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Testing;

use Illuminate\Http\Request;
use Marshmallow\BotShield\Captcha\CaptchaVerdict;
use Marshmallow\BotShield\Contracts\CaptchaDriver;
use Marshmallow\BotShield\Enums\CaptchaOutcome;

/**
 * Answers with whatever the test asked for, so captcha paths can be exercised
 * without reaching Google.
 */
final class FakeCaptchaDriver implements CaptchaDriver
{
    private CaptchaOutcome $outcome = CaptchaOutcome::Passed;

    private ?float $score = 0.9;

    public function passes(?float $score = 0.9): void
    {
        $this->outcome = CaptchaOutcome::Passed;
        $this->score = $score;
    }

    public function fails(): void
    {
        $this->outcome = CaptchaOutcome::Failed;
        $this->score = null;
    }

    public function scores(float $score): void
    {
        $this->score = $score;
        $this->outcome = CaptchaOutcome::Passed;
    }

    public function unavailable(): void
    {
        $this->outcome = CaptchaOutcome::Unavailable;
        $this->score = null;
    }

    public function verify(?string $token, Request $request, ?float $threshold = null): CaptchaVerdict
    {
        if (blank($token)) {
            return new CaptchaVerdict(CaptchaOutcome::MissingToken);
        }

        if ($this->outcome !== CaptchaOutcome::Passed) {
            return new CaptchaVerdict($this->outcome, $this->score, $threshold);
        }

        $threshold ??= 0.6;

        if ($this->score !== null && $this->score < $threshold) {
            return new CaptchaVerdict(CaptchaOutcome::LowScore, $this->score, $threshold);
        }

        return CaptchaVerdict::passed($this->score, $threshold);
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function name(): string
    {
        return 'fake';
    }

    public function siteKey(): string
    {
        return 'fake-site-key';
    }
}
