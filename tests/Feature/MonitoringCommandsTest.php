<?php

declare(strict_types=1);

use Marshmallow\BotShield\Enums\EventType;
use Marshmallow\BotShield\Models\BotShieldEvent;
use Marshmallow\BotShield\Monitoring\ScoreDistribution;

/**
 * @param  array<string, mixed>  $attributes
 */
function recordEvent(EventType $type, array $attributes = []): BotShieldEvent
{
    return BotShieldEvent::query()->create(array_merge([
        'type' => $type,
        'created_at' => now(),
    ], $attributes));
}

describe('bot-shield:stats', function () {
    it('warns when monitoring is disabled', function () {
        config()->set('bot-shield.monitoring.enabled', false);

        $this->artisan('bot-shield:stats')
            ->expectsOutputToContain('Monitoring is disabled')
            ->assertSuccessful();
    });

    it('warns when nothing was recorded', function () {
        $this->artisan('bot-shield:stats')
            ->expectsOutputToContain('No events recorded')
            ->assertSuccessful();
    });

    it('summarises each event type', function () {
        recordEvent(EventType::DetectorBlock, ['outcome' => 'blocked', 'form' => 'contact']);
        recordEvent(EventType::DetectorBlock, ['outcome' => 'blocked', 'form' => 'contact']);
        recordEvent(EventType::SuppressedException, ['outcome' => TypeError::class]);
        recordEvent(EventType::Honeypot, ['outcome' => 'tripped']);

        $this->artisan('bot-shield:stats')
            ->expectsOutputToContain('Blocked submissions')
            ->expectsOutputToContain('Suppressed exceptions')
            ->expectsOutputToContain('4 event(s) recorded in total')
            ->assertSuccessful();
    });

    it('leaves out events older than the requested window', function () {
        recordEvent(EventType::DetectorBlock, ['created_at' => now()->subDays(30)]);

        $this->artisan('bot-shield:stats', ['--days' => 7])
            ->expectsOutputToContain('No events recorded')
            ->assertSuccessful();
    });

    it('includes events inside the requested window', function () {
        recordEvent(EventType::DetectorBlock, ['created_at' => now()->subDays(30)]);

        $this->artisan('bot-shield:stats', ['--days' => 60])
            ->expectsOutputToContain('1 event(s) recorded in total')
            ->assertSuccessful();
    });

    it('reports the top user agents and addresses', function () {
        recordEvent(EventType::DetectorBlock, ['user_agent' => 'curl/8.7.1', 'ip' => '203.0.113.7']);

        $this->artisan('bot-shield:stats')
            ->expectsOutputToContain('curl/8.7.1')
            ->expectsOutputToContain('203.0.113.7')
            ->assertSuccessful();
    });
});

describe('bot-shield:scores', function () {
    it('warns when monitoring is disabled', function () {
        config()->set('bot-shield.monitoring.enabled', false);

        $this->artisan('bot-shield:scores')
            ->expectsOutputToContain('Monitoring is disabled')
            ->assertSuccessful();
    });

    it('warns when no scores were recorded', function () {
        $this->artisan('bot-shield:scores')
            ->expectsOutputToContain('No scored verifications')
            ->assertSuccessful();
    });

    it('ignores captcha events that carry no score', function () {
        recordEvent(EventType::Captcha, ['outcome' => 'missing-token']);

        $this->artisan('bot-shield:scores')
            ->expectsOutputToContain('No scored verifications')
            ->assertSuccessful();
    });

    it('refuses to suggest a threshold from too little data', function () {
        recordEvent(EventType::Captcha, ['outcome' => 'passed', 'score' => 0.9]);

        $this->artisan('bot-shield:scores')
            ->expectsOutputToContain('too few to read a shape into')
            ->assertSuccessful();
    });

    it('suggests a threshold once the clusters are clear', function () {
        foreach (range(1, 40) as $ignored) {
            recordEvent(EventType::Captcha, ['outcome' => 'low-score', 'score' => 0.1]);
        }

        foreach (range(1, 40) as $ignored) {
            recordEvent(EventType::Captcha, ['outcome' => 'passed', 'score' => 0.9]);
        }

        $this->artisan('bot-shield:scores')
            ->expectsOutputToContain('Suggested threshold')
            ->expectsOutputToContain('BOT_SHIELD_RECAPTCHA_SCORE=0.9')
            ->assertSuccessful();
    });

    it('says so when the current threshold already sits in the gap', function () {
        config()->set('bot-shield.captcha.score', 0.9);

        foreach (range(1, 40) as $ignored) {
            recordEvent(EventType::Captcha, ['outcome' => 'low-score', 'score' => 0.1]);
        }

        foreach (range(1, 40) as $ignored) {
            recordEvent(EventType::Captcha, ['outcome' => 'passed', 'score' => 0.9]);
        }

        $this->artisan('bot-shield:scores')
            ->expectsOutputToContain('already sits in the quiet stretch')
            ->assertSuccessful();
    });

    it('says so when the scores do not separate', function () {
        foreach (range(0, 9) as $bucket) {
            foreach (range(1, 10) as $ignored) {
                recordEvent(EventType::Captcha, ['outcome' => 'passed', 'score' => $bucket / 10]);
            }
        }

        $this->artisan('bot-shield:scores')
            ->expectsOutputToContain('do not separate into distinct clusters')
            ->assertSuccessful();
    });

    it('can be limited to a single form', function () {
        foreach (range(1, ScoreDistribution::MINIMUM_SAMPLES + 10) as $ignored) {
            recordEvent(EventType::Captcha, ['outcome' => 'passed', 'score' => 0.9, 'form' => 'contact']);
        }

        recordEvent(EventType::Captcha, ['outcome' => 'low-score', 'score' => 0.1, 'form' => 'newsletter']);

        $this->artisan('bot-shield:scores', ['--form' => 'contact'])
            ->expectsOutputToContain('for form [contact]')
            ->expectsOutputToContain('Samples')
            ->assertSuccessful();
    });
});
