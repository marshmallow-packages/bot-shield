<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Captcha\Drivers;

use Marshmallow\BotShield\Captcha\CaptchaVerdict;
use Marshmallow\BotShield\Enums\CaptchaOutcome;

/**
 * Score based reCAPTCHA. Runs invisibly and returns a score per request, so
 * every verification is also a data point for tuning the threshold.
 */
final class GoogleV3Driver extends GoogleDriver
{
    public function name(): string
    {
        return 'google-v3';
    }

    protected function verdictFrom(array $payload, ?float $threshold): CaptchaVerdict
    {
        $threshold ??= $this->defaultThreshold();
        $score = $this->score($payload);

        if (! $this->succeeded($payload)) {
            return new CaptchaVerdict(
                CaptchaOutcome::Failed,
                $score,
                $threshold,
                $this->errorCodes($payload),
            );
        }

        if ($score < $threshold) {
            return new CaptchaVerdict(CaptchaOutcome::LowScore, $score, $threshold);
        }

        return CaptchaVerdict::passed($score, $threshold);
    }

    /**
     * @param  array<mixed, mixed>  $payload
     */
    private function score(array $payload): float
    {
        $score = $payload['score'] ?? null;

        return is_numeric($score) ? (float) $score : 0.0;
    }

    private function defaultThreshold(): float
    {
        return (float) $this->config->get('bot-shield.captcha.score', 0.6);
    }
}
