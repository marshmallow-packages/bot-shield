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
        public ?string $hostname = null,
        public ?string $action = null,
        public ?int $tokenAge = null,
    ) {}

    public static function skipped(): self
    {
        return new self(CaptchaOutcome::Skipped);
    }

    public static function passed(
        ?float $score = null,
        ?float $threshold = null,
        ?string $hostname = null,
        ?string $action = null,
        ?int $tokenAge = null,
    ): self {
        return new self(CaptchaOutcome::Passed, $score, $threshold, [], $hostname, $action, $tokenAge);
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
            'hostname' => $this->hostname,
            'action' => $this->action,
            'token_age' => $this->tokenAge,
        ];
    }

    /**
     * What the provider said beyond the outcome and score, for the event row.
     * Empty when there is nothing to say, so a skipped or missing token
     * verification stores no context at all.
     *
     * @return array<string, mixed>
     */
    public function diagnostics(): array
    {
        return array_filter([
            'error_codes' => $this->errorCodes === [] ? null : $this->errorCodes,
            'hostname' => $this->hostname,
            'action' => $this->action,
            'token_age' => $this->tokenAge,
            'threshold' => $this->threshold,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
