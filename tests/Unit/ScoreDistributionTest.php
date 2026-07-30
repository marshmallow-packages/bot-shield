<?php

declare(strict_types=1);

use Marshmallow\BotShield\Monitoring\ScoreDistribution;

/**
 * @return list<float>
 */
function scoresAt(float $score, int $times): array
{
    return array_fill(0, $times, $score);
}

it('buckets scores in tenths', function () {
    $distribution = ScoreDistribution::fromScores([0.0, 0.05, 0.15, 0.9, 1.0]);

    expect($distribution->total)->toBe(5)
        ->and($distribution->buckets[0])->toBe(2)
        ->and($distribution->buckets[1])->toBe(1)
        ->and($distribution->buckets[9])->toBe(2);
});

it('ignores values that are not numbers', function () {
    $distribution = ScoreDistribution::fromScores([0.5, null, 'nope', 0.5]);

    expect($distribution->total)->toBe(2);
});

it('clamps scores outside the expected range', function () {
    $distribution = ScoreDistribution::fromScores([-1.0, 5.0]);

    expect($distribution->buckets[0])->toBe(1)
        ->and($distribution->buckets[9])->toBe(1);
});

it('reports the bounds of a bucket', function () {
    $distribution = ScoreDistribution::fromScores([0.5]);

    expect($distribution->lowerBound(6))->toBe(0.6)
        ->and($distribution->upperBound(6))->toBe(0.7);
});

it('suggests a threshold in the quiet stretch between two clusters', function () {
    $distribution = ScoreDistribution::fromScores([
        ...scoresAt(0.1, 40),
        ...scoresAt(0.2, 20),
        ...scoresAt(0.9, 60),
    ]);

    expect($distribution->suggestedThreshold())->toBe(0.9);
});

it('picks the widest stretch when there is more than one', function () {
    $distribution = ScoreDistribution::fromScores([
        ...scoresAt(0.0, 30),
        ...scoresAt(0.2, 30),
        ...scoresAt(0.8, 30),
    ]);

    expect($distribution->suggestedThreshold())->toBe(0.8);
});

it('suggests nothing below the minimum sample size', function () {
    $distribution = ScoreDistribution::fromScores([
        ...scoresAt(0.1, 5),
        ...scoresAt(0.9, 5),
    ]);

    expect($distribution->total)->toBeLessThan(ScoreDistribution::MINIMUM_SAMPLES)
        ->and($distribution->suggestedThreshold())->toBeNull();
});

it('suggests nothing when the scores do not separate', function () {
    $scores = [];

    foreach (range(0, 9) as $bucket) {
        $scores = [...$scores, ...scoresAt($bucket / 10, 10)];
    }

    $distribution = ScoreDistribution::fromScores($scores);

    expect($distribution->total)->toBe(100)
        ->and($distribution->suggestedThreshold())->toBeNull();
});

it('suggests nothing when everything sits in one cluster', function () {
    $distribution = ScoreDistribution::fromScores(scoresAt(0.9, 80));

    expect($distribution->suggestedThreshold())->toBeNull();
});
