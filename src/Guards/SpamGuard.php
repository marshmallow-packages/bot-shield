<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Guards;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Marshmallow\BotShield\Concerns\ProtectsAgainstSpam;
use Marshmallow\BotShield\Contracts\RecordsEvents;
use Marshmallow\BotShield\Enums\EventType;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;
use Spatie\Honeypot\Events\SpamDetectedEvent;
use Spatie\Honeypot\Exceptions\SpamException;
use Spatie\Honeypot\Honeypot;
use Spatie\Honeypot\Http\Livewire\Concerns\HoneypotData;
use Spatie\Honeypot\SpamProtection;
use Symfony\Component\HttpKernel\Exception\HttpException;

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

        if (! $data instanceof HoneypotData) {
            throw new RuntimeException(sprintf(
                'The [%s] trait needs a public %s property. Add "public %s $honeypotData;" to [%s].',
                ProtectsAgainstSpam::class,
                HoneypotData::class,
                class_basename(HoneypotData::class),
                $component::class,
            ));
        }

        try {
            $this->container->make(SpamProtection::class)->check($data->toArray());
        } catch (SpamException) {
            $request = $this->container->make(Request::class);

            $this->events->dispatch(new SpamDetectedEvent($request));

            $this->recorder->record(EventType::Honeypot, 'tripped', [
                'component' => $component::class,
            ]);

            throw new HttpException(
                (int) $this->config->get('bot-shield.honeypot.status', 403),
                (string) trans('bot-shield::messages.spam_detected'),
            );
        }
    }

    private function honeypotDataOf(object $component): ?HoneypotData
    {
        foreach ((new ReflectionClass($component))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getType()?->__toString() !== HoneypotData::class) {
                continue;
            }

            $value = $property->getValue($component);

            if ($value instanceof HoneypotData) {
                return $value;
            }
        }

        return null;
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
