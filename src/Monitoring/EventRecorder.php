<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Monitoring;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Marshmallow\BotShield\Enums\EventType;
use Marshmallow\BotShield\Models\BotShieldEvent;
use Throwable;

/**
 * Writes one row per notable event, so questions like "is 0.6 too strict for
 * this audience" can be answered from real traffic instead of guesswork.
 */
final class EventRecorder
{
    public function __construct(
        private readonly Repository $config,
        private readonly Container $container,
    ) {}

    /**
     * Recording is best effort by design. This runs inside form submissions and
     * inside the exception handler, so a missing table or an unreachable
     * database must never turn monitoring into an outage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function record(EventType $type, ?string $outcome = null, array $attributes = []): void
    {
        if (! $this->enabled()) {
            return;
        }

        try {
            BotShieldEvent::query()->create($this->attributesFor($type, $outcome, $attributes));
        } catch (Throwable) {
            // The structured log channel remains the durable record.
        }
    }

    public function enabled(): bool
    {
        if (! $this->truthy('bot-shield.enabled')) {
            return false;
        }

        return $this->truthy('bot-shield.monitoring.enabled');
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function attributesFor(EventType $type, ?string $outcome, array $attributes): array
    {
        $request = $this->currentRequest();

        return array_merge([
            'type' => $type,
            'outcome' => $outcome,
            'score' => null,
            'form' => null,
            'component' => null,
            'action' => null,
            'url' => $request?->fullUrl(),
            'ip' => $this->ip($request),
            'user_agent' => $request?->userAgent(),
            'created_at' => now(),
        ], array_filter(
            $attributes,
            static fn (mixed $value): bool => $value !== null,
        ));
    }

    private function ip(?Request $request): ?string
    {
        $ip = $request?->ip();

        if ($ip === null) {
            return null;
        }

        if (! $this->truthy('bot-shield.monitoring.hash_ips')) {
            return $ip;
        }

        return hash('sha256', $ip);
    }

    private function currentRequest(): ?Request
    {
        if (! $this->container->bound('request')) {
            return null;
        }

        return $this->container->make('request');
    }

    private function truthy(string $configKey): bool
    {
        return filter_var(
            $this->config->get($configKey, false),
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE,
        ) === true;
    }
}
