<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Marshmallow\BotShield\Enums\EventType;
use Marshmallow\BotShield\Models\BotShieldEvent;
use Marshmallow\BotShield\Monitoring\EventRecorder;

class StatsCommand extends Command
{
    protected $signature = 'bot-shield:stats {--days=7 : How many days back to summarise}';

    protected $description = 'Summarise what Bot Shield blocked and suppressed.';

    public function handle(EventRecorder $recorder): int
    {
        if (! $recorder->enabled()) {
            $this->components->warn('Monitoring is disabled, so there is nothing recorded to summarise.');

            return self::SUCCESS;
        }

        $days = max((int) $this->option('days'), 1);
        $since = Carbon::now()->subDays($days);

        $this->components->info("Bot Shield activity over the last {$days} day(s).");

        $total = $this->since($since)->count();

        if ($total === 0) {
            $this->components->warn('No events recorded in this period.');

            return self::SUCCESS;
        }

        $this->summariseTypes($since);
        $this->summariseForms($since);
        $this->summariseAgents($since);
        $this->summariseAddresses($since);

        $this->newLine();
        $this->components->info("{$total} event(s) recorded in total.");

        return self::SUCCESS;
    }

    private function summariseTypes(Carbon $since): void
    {
        $counts = $this->since($since)
            ->selectRaw('type, count(*) as aggregate')
            ->groupBy('type')
            ->pluck('aggregate', 'type');

        $rows = [];

        foreach (EventType::cases() as $type) {
            $rows[] = [$type->label(), (int) ($counts[$type->value] ?? 0)];
        }

        $this->table(['Event', 'Count'], $rows);
    }

    private function summariseForms(Carbon $since): void
    {
        $rows = $this->since($since)
            ->whereNotNull('form')
            ->selectRaw('form, count(*) as aggregate')
            ->groupBy('form')
            ->orderByDesc('aggregate')
            ->limit(10)
            ->get()
            ->map(fn (BotShieldEvent $event): array => [$event->form, $event->getAttribute('aggregate')])
            ->all();

        if ($rows === []) {
            return;
        }

        $this->table(['Form', 'Events'], $rows);
    }

    private function summariseAgents(Carbon $since): void
    {
        $rows = $this->since($since)
            ->whereNotNull('user_agent')
            ->selectRaw('user_agent, count(*) as aggregate')
            ->groupBy('user_agent')
            ->orderByDesc('aggregate')
            ->limit(10)
            ->get()
            ->map(fn (BotShieldEvent $event): array => [
                str($event->user_agent ?? '')->limit(70)->value(),
                $event->getAttribute('aggregate'),
            ])
            ->all();

        if ($rows === []) {
            return;
        }

        $this->table(['Top user agents', 'Events'], $rows);
    }

    private function summariseAddresses(Carbon $since): void
    {
        $rows = $this->since($since)
            ->whereNotNull('ip')
            ->selectRaw('ip, count(*) as aggregate')
            ->groupBy('ip')
            ->orderByDesc('aggregate')
            ->limit(10)
            ->get()
            ->map(fn (BotShieldEvent $event): array => [
                str($event->ip ?? '')->limit(24)->value(),
                $event->getAttribute('aggregate'),
            ])
            ->all();

        if ($rows === []) {
            return;
        }

        $this->table(['Top addresses', 'Events'], $rows);
    }

    /**
     * @return Builder<BotShieldEvent>
     */
    private function since(Carbon $since): Builder
    {
        return BotShieldEvent::query()->where('created_at', '>=', $since);
    }
}
