<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Marshmallow\BotShield\Enums\EventType;

/**
 * @property EventType $type
 * @property string|null $outcome
 * @property float|null $score
 * @property string|null $form
 * @property string|null $component
 * @property string|null $action
 * @property string|null $url
 * @property string|null $ip
 * @property string|null $user_agent
 */
class BotShieldEvent extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    protected $table = 'bot_shield_events';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EventType::class,
            'score' => 'float',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return Builder<covariant static>
     */
    public function prunable(): Builder
    {
        $days = (int) config('bot-shield.monitoring.retention_days', 30);

        return static::query()->where('created_at', '<', now()->subDays(max($days, 1)));
    }

    public function getConnectionName(): ?string
    {
        $connection = config('bot-shield.monitoring.connection');

        return is_string($connection) && $connection !== '' ? $connection : parent::getConnectionName();
    }
}
