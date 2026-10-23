<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Guards;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Marshmallow\BotShield\Concerns\ProtectsAgainstSpam;
use Marshmallow\BotShield\Contracts\RecordsEvents;
use Marshmallow\BotShield\Enums\EventType;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use RuntimeException;
use Spatie\Honeypot\Events\SpamDetectedEvent;
use Spatie\Honeypot\Exceptions\SpamException;
use Spatie\Honeypot\Honeypot;
use Spatie\Honeypot\Http\Livewire\Concerns\HoneypotData;
use Spatie\Honeypot\SpamProtection;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Wraps spatie/laravel-honeypot so the package keeps a stable API of its own,
 * and so an install without that package fails with an actionable message
 * instead of a class not found error.
 */
final class SpamGuard
{
    public function __construct(
        private readonly Repository $config,
        private readonly Container $container,
        private readonly Dispatcher $events,
        private readonly RecordsEvents $recorder,
    ) {}

    public function enabled(): bool
    {
        if (! $this->truthy('bot-shield.enabled')) {
            return false;
        }

        return $this->truthy('bot-shield.honeypot.enabled');
    }

    public function isAvailable(): bool
    {
        return class_exists(Honeypot::class);
    }

    public function ensureAvailable(): void
    {
        if ($this->isAvailable()) {
            return;
        }

        throw new RuntimeException(
            'The Bot Shield honeypot requires spatie/laravel-honeypot. '
            .'Run: composer require spatie/laravel-honeypot '
            .'or disable it with BOT_SHIELD_HONEYPOT_ENABLED=false.',
        );
    }

    public function honeypot(): Honeypot
    {
        $this->ensureAvailable();

        return $this->container->make(Honeypot::class);
    }

    /**
     * Check the honeypot fields carried by a Livewire component.
     */
    public function guard(object $component): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->ensureAvailable();

        $data = $this->honeypotDataOf($component);

        try {
            $this->container->make(SpamProtection::class)->check($data->toArray());
        } catch (SpamException) {
            $request = $this->container->make(Request::class);

            $this->events->dispatch(new SpamDetectedEvent($request));

            $this->recorder->record(EventType::Honeypot, 'tripped', [
                'component' => $component::class,
                'context' => $this->reasonFor($data->toArray()),
            ]);

            throw new HttpException(
                (int) $this->config->get('bot-shield.honeypot.status', 403),
                (string) trans('bot-shield::messages.spam_detected'),
            );
        }
    }

    /**
     * The property may be declared nullable, as a component that assigns it in
     * mount() commonly does, but it must hold a value by the time the action runs.
     */
    private function honeypotDataOf(object $component): HoneypotData
    {
        foreach ((new ReflectionClass($component))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $type = $property->getType();

            if (! $type instanceof ReflectionNamedType || $type->getName() !== HoneypotData::class) {
                continue;
            }

            $value = $property->isInitialized($component) ? $property->getValue($component) : null;

            if ($value instanceof HoneypotData) {
                return $value;
            }

            throw new RuntimeException(sprintf(
                '[%s::$%s] is null. Assign "new %s" to it in mount() so the honeypot fields can be checked.',
                $component::class,
                $property->getName(),
                class_basename(HoneypotData::class),
            ));
        }

        throw new RuntimeException(sprintf(
            'The [%s] trait needs a public %s property. Add "public %s $honeypotData;" to [%s].',
            ProtectsAgainstSpam::class,
            HoneypotData::class,
            class_basename(HoneypotData::class),
            $component::class,
        ));
    }

    /**
     * Spatie's check throws a bare SpamException, so the checks are repeated
     * here in its order to name the one that fired. Diagnostic only: it never
     * throws, and never records the form payload.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function reasonFor(array $fields): array
    {
        try {
            $nameField = (string) $this->config->get('honeypot.name_field_name', '');
            $validFromField = (string) $this->config->get('honeypot.valid_from_field_name', '');

            $trapKey = null;

            foreach (array_keys($fields) as $key) {
                if ($nameField !== '' && str_starts_with($key, $nameField)) {
                    $trapKey = $key;

                    break;
                }
            }

            if ($trapKey === null) {
                return ['check' => 'field_missing'];
            }

            $trap = $fields[$trapKey];

            if (! in_array($trap, [null, ''], true)) {
                return [
                    'check' => 'field_filled',
                    'value_length' => is_string($trap) ? mb_strlen($trap) : null,
                ];
            }

            $validFrom = $fields[$validFromField] ?? null;

            if (! is_string($validFrom) || $validFrom === '') {
                return ['check' => 'timestamp_missing'];
            }

            $timestamp = $this->container->make(Encrypter::class)->decrypt($validFrom);

            if (! is_numeric($timestamp)) {
                return ['check' => 'timestamp_invalid'];
            }

            $renderedAt = (int) $timestamp - (int) $this->config->get('honeypot.amount_of_seconds', 0);

            return [
                'check' => 'too_fast',
                'seconds_since_render' => max(0, Date::now()->getTimestamp() - $renderedAt),
            ];
        } catch (Throwable) {
            return ['check' => 'timestamp_invalid'];
        }
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
