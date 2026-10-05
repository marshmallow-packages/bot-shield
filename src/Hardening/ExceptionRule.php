<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Hardening;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Marshmallow\BotShield\Contracts\ExceptionCondition;
use Throwable;

/**
 * A single configured exception match: an exception class, optionally narrowed
 * by message needles that must all be present and by a request condition.
 * "always" marks shapes a browser using the normal UI cannot produce, so they
 * need no bot verdict.
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
        public ?string $when = null,
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
        $when = $rule['when'] ?? null;

        return new self(
            class: is_string($class) ? $class : '',
            contains: array_values(array_filter(Arr::wrap($rule['contains'] ?? []), is_string(...))),
            always: ($rule['always'] ?? false) === true,
            when: is_string($when) ? $when : null,
        );
    }

    /**
     * Checks the exception and everything it wraps: Blade rethrows a
     * component error as a ViewException, which would otherwise never match.
     * A rule with a condition never matches without a request to check.
     */
    public function matches(Throwable $exception, ?Request $request = null): bool
    {
        if ($this->class === '') {
            return false;
        }

        for ($depth = 0, $current = $exception; $current instanceof Throwable && $depth < self::MAX_DEPTH; $depth++, $current = $current->getPrevious()) {
            if ($this->matchesOne($current, $request)) {
                return true;
            }
        }

        return false;
    }

    private function matchesOne(Throwable $exception, ?Request $request): bool
    {
        if (! $exception instanceof $this->class) {
            return false;
        }

        foreach ($this->contains as $needle) {
            if (! str_contains($exception->getMessage(), $needle)) {
                return false;
            }
        }

        if ($this->when === null) {
            return true;
        }

        return $request instanceof Request && $this->condition()?->matches($exception, $request) === true;
    }

    private function condition(): ?ExceptionCondition
    {
        if ($this->when === null || ! is_subclass_of($this->when, ExceptionCondition::class)) {
            return null;
        }

        $condition = Container::getInstance()->make($this->when);

        return $condition instanceof ExceptionCondition ? $condition : null;
    }
}
