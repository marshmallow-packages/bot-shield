<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Hardening\Conditions;

use Illuminate\Http\Request;
use Marshmallow\BotShield\Contracts\ExceptionCondition;
use Throwable;

/**
 * Livewire throws the same exception when a component mutates its own
 * #[Reactive] prop, which is a real bug. Only a request whose own updates name
 * that prop on that component is forged, since the UI never sends those.
 */
final class ForgedReactivePropUpdate implements ExceptionCondition
{
    public function matches(Throwable $exception, Request $request): bool
    {
        if (preg_match('/Cannot mutate reactive prop \[(.+)\] in component: \[(.+)\]$/s', $exception->getMessage(), $matches) !== 1) {
            return false;
        }

        [, $prop, $component] = $matches;

        $components = $request->input('components');

        if (! is_array($components)) {
            return false;
        }

        foreach ($components as $payload) {
            if (is_array($payload) && $this->componentName($payload) === $component && $this->updates($payload, $prop)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<mixed, mixed>  $payload
     */
    private function componentName(array $payload): ?string
    {
        $snapshot = is_string($payload['snapshot'] ?? null) ? json_decode($payload['snapshot'], true) : null;

        if (! is_array($snapshot) || ! is_array($snapshot['memo'] ?? null)) {
            return null;
        }

        $name = $snapshot['memo']['name'] ?? null;

        return is_string($name) ? $name : null;
    }

    /**
     * @param  array<mixed, mixed>  $payload
     */
    private function updates(array $payload, string $prop): bool
    {
        if (! is_array($payload['updates'] ?? null)) {
            return false;
        }

        foreach (array_keys($payload['updates']) as $key) {
            if ($key === $prop || str_starts_with((string) $key, $prop.'.')) {
                return true;
            }
        }

        return false;
    }
}
