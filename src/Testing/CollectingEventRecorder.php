<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Testing;

use Marshmallow\BotShield\Contracts\RecordsEvents;
use Marshmallow\BotShield\Enums\EventType;

/**
 * Keeps events in memory so app level tests can assert on them without a
 * database table.
 */
final class CollectingEventRecorder implements RecordsEvents
{
    /**
     * @var list<array<string, mixed>>
     */
    private array $events = [];

    public function record(EventType $type, ?string $outcome = null, array $attributes = []): void
    {
        $this->events[] = array_merge($attributes, [
            'type' => $type,
            'outcome' => $outcome,
        ]);
    }

    public function enabled(): bool
    {
        return true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return $this->events;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function ofType(EventType $type): array
    {
        return array_values(array_filter(
            $this->events,
            static fn (array $event): bool => $event['type'] === $type,
        ));
    }

    public function flush(): void
    {
        $this->events = [];
    }
}
