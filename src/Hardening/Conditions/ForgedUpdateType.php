<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Hardening\Conditions;

use Illuminate\Http\Request;
use Marshmallow\BotShield\Contracts\ExceptionCondition;
use Throwable;

/**
 * An update that sends an array to a prop the snapshot holds as a scalar. A
 * text input can never send one, so the request was hand-edited. Livewire
 * casts strings into int props itself, so only arrays count.
 */
final class ForgedUpdateType implements ExceptionCondition
{
    public function matches(Throwable $exception, Request $request): bool
    {
        $components = $request->input('components');

        if (! is_array($components)) {
            return false;
        }

        foreach ($components as $payload) {
            if (is_array($payload) && $this->forges($payload)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<mixed, mixed>  $payload
     */
    private function forges(array $payload): bool
    {
        $snapshot = is_string($payload['snapshot'] ?? null) ? json_decode($payload['snapshot'], true) : null;
        $data = is_array($snapshot) ? ($snapshot['data'] ?? null) : null;
        $updates = $payload['updates'] ?? null;

        if (! is_array($data) || ! is_array($updates)) {
            return false;
        }

        foreach ($updates as $key => $value) {
            if (is_array($value) && array_key_exists($key, $data) && is_scalar($data[$key])) {
                return true;
            }
        }

        return false;
    }
}
