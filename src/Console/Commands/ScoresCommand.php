<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Marshmallow\BotShield\Enums\EventType;
use Marshmallow\BotShield\Models\BotShieldEvent;
use Marshmallow\BotShield\Monitoring\EventRecorder;
use Marshmallow\BotShield\Monitoring\ScoreDistribution;

class ScoresCommand extends Command
{
    protected $signature = 'bot-shield:scores
        {--days=30 : How many days back to read}
        {--form= : Limit to a single form}';

    protected $description = 'Show the captcha score distribution and a suggested threshold.';

    public function handle(EventRecorder $recorder): int
    {
        if (! $recorder->enabled()) {
            $this->components->warn('Monitoring is disabled, so no scores have been recorded.');

            return self::SUCCESS;
        }

        $days = max((int) $this->option('days'), 1);
        $form = $this->option('form');

        $query = BotShieldEvent::query()
            ->where('type', EventType::Captcha)
            ->whereNotNull('score')
            ->where('created_at', '>=', Carbon::now()->subDays($days));

        if (is_string($form) && $form !== '') {
            $query->where('form', $form);
        }

        $distribution = ScoreDistribution::fromScores($query->pluck('score'));

        $scope = is_string($form) && $form !== '' ? " for form [{$form}]" : '';

        $this->components->info("Captcha scores over the last {$days} day(s){$scope}.");

        if ($distribution->total === 0) {
            $this->components->warn('No scored verifications recorded in this period.');

            return self::SUCCESS;
        }

        $this->renderHistogram($distribution);
        $this->renderThreshold($distribution);

        return self::SUCCESS;
    }

    private function renderHistogram(ScoreDistribution $distribution): void
    {
        $peak = $distribution->peak();
        $rows = [];

        foreach ($distribution->buckets as $bucket => $count) {
            $share = $count / $distribution->total * 100;
            $width = $peak === 0 ? 0 : (int) round($count / $peak * 40);

            $rows[] = [
                sprintf('%.1f to %.1f', $distribution->lowerBound($bucket), $distribution->upperBound($bucket)),
                $count,
                sprintf('%5.1f%%', $share),
                str_repeat('#', $width),
            ];
        }

        $this->table(['Score', 'Count', 'Share', ''], $rows);
    }

    private function renderThreshold(ScoreDistribution $distribution): void
    {
        $current = (float) config('bot-shield.captcha.score', 0.6);
        $suggested = $distribution->suggestedThreshold();

        $this->components->twoColumnDetail('Samples', (string) $distribution->total);
        $this->components->twoColumnDetail('Current threshold', sprintf('%.1f', $current));

        if ($distribution->total < ScoreDistribution::MINIMUM_SAMPLES) {
            $this->components->warn(sprintf(
                'Fewer than %d samples, which is too few to read a shape into. Leaving the threshold alone.',
                ScoreDistribution::MINIMUM_SAMPLES,
            ));

            return;
        }

        if ($suggested === null) {
            $this->components->warn(
                'Scores do not separate into distinct clusters, so there is no obvious place to draw the line. '
                .'Leaving the threshold alone.',
            );

            return;
        }

        $this->components->twoColumnDetail('Suggested threshold', sprintf('%.1f', $suggested));

        if (abs($suggested - $current) < 0.05) {
            $this->components->info('The current threshold already sits in the quiet stretch between the clusters.');

            return;
        }

        $this->components->info(sprintf(
            'Consider BOT_SHIELD_RECAPTCHA_SCORE=%.1f. Verify against the histogram before changing it.',
            $suggested,
        ));
    }
}
