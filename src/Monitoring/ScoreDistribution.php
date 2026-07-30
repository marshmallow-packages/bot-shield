<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Monitoring;

/**
 * Captcha scores bucketed in tenths, which is the resolution reCAPTCHA actually
 * reports.
 */
final readonly class ScoreDistribution
{
    public const int BUCKETS = 10;

    /**
     * Below this many samples the shape of the distribution is noise, so no
     * threshold is suggested rather than a confident sounding guess.
     */
    public const int MINIMUM_SAMPLES = 50;

    /**
     * @param  non-empty-array<int, int>  $buckets  Counts keyed by tenth, 0 through 9.
     */
    private function __construct(
        public array $buckets,
        public int $total,
    ) {}

    /**
     * @param  iterable<mixed>  $scores
     */
    public static function fromScores(iterable $scores): self
    {
        $buckets = array_fill(0, self::BUCKETS, 0);
        $total = 0;

        foreach ($scores as $score) {
            if (! is_numeric($score)) {
                continue;
            }

            $bucket = (int) floor((float) $score * self::BUCKETS);
            $bucket = max(0, min(self::BUCKETS - 1, $bucket));

            $buckets[$bucket]++;
            $total++;
        }

        return new self($buckets, $total);
    }

    /**
     * The count in the busiest bucket, which sets the scale of the histogram.
     */
    public function peak(): int
    {
        $peak = 0;

        foreach ($this->buckets as $count) {
            $peak = max($peak, $count);
        }

        return $peak;
    }

    public function lowerBound(int $bucket): float
    {
        return $bucket / self::BUCKETS;
    }

    public function upperBound(int $bucket): float
    {
        return ($bucket + 1) / self::BUCKETS;
    }

    /**
     * Real traffic tends to split into a human cluster near the top and a bot
     * cluster near the bottom, with a quiet stretch between them. The widest
     * empty stretch that has traffic on both sides is the safest place to draw
     * the line, so its upper edge is suggested.
     */
    public function suggestedThreshold(): ?float
    {
        if ($this->total < self::MINIMUM_SAMPLES) {
            return null;
        }

        $widestRun = null;
        $widestLength = 0;
        $runStart = null;

        foreach (range(0, self::BUCKETS - 1) as $bucket) {
            if ($this->buckets[$bucket] > 0) {
                $runStart = null;

                continue;
            }

            if (! $this->hasTrafficBelow($bucket)) {
                continue;
            }

            if (! $this->hasTrafficAbove($bucket)) {
                continue;
            }

            $runStart ??= $bucket;
            $length = $bucket - $runStart + 1;

            if ($length <= $widestLength) {
                continue;
            }

            $widestLength = $length;
            $widestRun = $bucket;
        }

        if ($widestRun === null) {
            return null;
        }

        return $this->upperBound($widestRun);
    }

    private function hasTrafficBelow(int $bucket): bool
    {
        foreach (range(0, $bucket) as $candidate) {
            if ($this->buckets[$candidate] > 0) {
                return true;
            }
        }

        return false;
    }

    private function hasTrafficAbove(int $bucket): bool
    {
        foreach (range($bucket, self::BUCKETS - 1) as $candidate) {
            if ($this->buckets[$candidate] > 0) {
                return true;
            }
        }

        return false;
    }
}
