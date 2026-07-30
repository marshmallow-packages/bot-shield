<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Hardening;

use Illuminate\Support\Arr;
use Throwable;

/**
 * A single configured exception match: an exception class, optionally narrowed
 * by message needles that must all be present.
 */
final readonly class ExceptionRule
{
    /**
     * @param  list<string>  $contains
     */
    public function __construct(
        public string $class,
        public array $contains = [],
    ) {}

    /**
     * Rules come from a user editable config file, so anything malformed
     * degrades into a rule that never matches rather than a type error.
     *
     * @param  array<mixed, mixed>  $rule
     */
    public static function fromArray(array $rule): self
    {
        $class = $rule['class'] ?? null;

        return new self(
            class: is_string($class) ? $class : '',
            contains: array_values(array_filter(Arr::wrap($rule['contains'] ?? []), is_string(...))),
        );
    }

    public function matches(Throwable $exception): bool
    {
        if ($this->class === '') {
            return false;
        }

        if (! $exception instanceof $this->class) {
            return false;
        }

        foreach ($this->contains as $needle) {
            if (! str_contains($exception->getMessage(), $needle)) {
                return false;
            }
        }

        return true;
    }
}
