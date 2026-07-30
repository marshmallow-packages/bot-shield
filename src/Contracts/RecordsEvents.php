<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Contracts;

use Marshmallow\BotShield\Enums\EventType;

interface RecordsEvents
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function record(EventType $type, ?string $outcome = null, array $attributes = []): void;

    public function enabled(): bool;
}
