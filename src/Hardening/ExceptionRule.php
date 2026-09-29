<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Hardening;

use Illuminate\Support\Arr;
use Throwable;

/**
 * A single configured exception match: an exception class, optionally narrowed
 * by message needles that must all be present. "always" marks shapes a browser
 * using the normal UI cannot produce, so they need no bot verdict.
 */
final readonly class ExceptionRule
{
    private const int MAX_DEPTH = 10;

    /**
     * @param  list<string>  $contains
     */
    public function __construct(
        public string $class,
        public array $contains = [],
        public bool $always = false,
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
            always: ($rule['always'] ?? false) === true,
        );
    }

    /**
     * Checks the exception and everything it wraps: Blade rethrows a
     * component error as a ViewException, which would otherwise never match.
     */
    public function matches(Throwable $exception): bool
    {
        if ($this->class === '') {
            return false;
        }

        for ($depth = 0, $current = $exception; $current instanceof Throwable && $depth < self::MAX_DEPTH; $depth++, $current = $current->getPrevious()) {
            if ($this->matchesOne($current)) {
                return true;
            }
        }

        return false;
    }

    private function matchesOne(Throwable $exception): bool
    {
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
