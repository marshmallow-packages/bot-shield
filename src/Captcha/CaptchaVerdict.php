<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Captcha;

use Marshmallow\BotShield\Enums\CaptchaOutcome;

final readonly class CaptchaVerdict
{
    /**
     * @param  list<string>  $errorCodes
     */
    public function __construct(
        public CaptchaOutcome $outcome,
        public ?float $score = null,
        public ?float $threshold = null,
        public array $errorCodes = [],
    ) {}

    public static function skipped(): self
    {
        return new self(CaptchaOutcome::Skipped);
    }

    public static function passed(?float $score = null, ?float $threshold = null): self
    {
        return new self(CaptchaOutcome::Passed, $score, $threshold);
    }

    public function passes(): bool
    {
        return match ($this->outcome) {
            CaptchaOutcome::Skipped, CaptchaOutcome::Passed => true,
            default => false,
        };
    }

    public function fails(): bool
    {
        return ! $this->passes();
    }

    public function message(): string
    {
        return (string) trans($this->outcome->messageKey());
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [
            'outcome' => $this->outcome->value,
            'score' => $this->score,
            'threshold' => $this->threshold,
            'error_codes' => $this->errorCodes,
        ];
    }
}
