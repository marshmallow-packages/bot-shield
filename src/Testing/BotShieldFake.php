<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Testing;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Marshmallow\BotShield\BotShield;
use Marshmallow\BotShield\Captcha\CaptchaManager;
use Marshmallow\BotShield\Contracts\BotDetector;
use Marshmallow\BotShield\Contracts\RecordsEvents;
use Marshmallow\BotShield\Enums\EventType;
use Marshmallow\BotShield\Hardening\ExceptionHardening;
use PHPUnit\Framework\Assert;

/**
 * Swapped in by BotShield::fake(). Captcha answers are scripted, events are kept
 * in memory, and the assertions read from there.
 */
final class BotShieldFake extends BotShield
{
    public function __construct(
        ExceptionHardening $hardening,
        BotDetector $detector,
        private readonly Container $container,
        private readonly Repository $config,
        private readonly CollectingEventRecorder $recorder,
        private readonly FakeCaptchaDriver $captcha,
    ) {
        parent::__construct($hardening, $detector);
    }

    /**
     * Rebind the seams and forget the singletons that already captured them.
     *
     * The driver the application configured is read before it is replaced, so
     * the fake keeps answering as that driver. Otherwise the renderer would see
     * an unfamiliar name, decide the site is not on v3, and serve the checkbox
     * widget to a test whose application ships the invisible one.
     */
    public function activate(?string $driver = null): self
    {
        $this->container->instance(RecordsEvents::class, $this->recorder);
        $this->container->instance(FakeCaptchaDriver::class, $this->captcha);

        $this->captcha->impersonate($driver ?? $this->configuredDriver());

        $this->config->set('bot-shield.enabled', true);
        $this->config->set('bot-shield.captcha.enabled', true);
        $this->config->set('bot-shield.captcha.driver', FakeCaptchaDriver::class);

        $this->container->forgetInstance(CaptchaManager::class);
        $this->container->forgetInstance(ExceptionHardening::class);
        $this->container->forgetInstance(BotDetector::class);

        return $this;
    }

    public function captchaPasses(float $score = 0.9): self
    {
        $this->captcha->passes($score);

        return $this;
    }

    public function captchaFails(): self
    {
        $this->captcha->fails();

        return $this;
    }

    public function captchaScores(float $score): self
    {
        $this->captcha->scores($score);

        return $this;
    }

    public function captchaUnavailable(): self
    {
        $this->captcha->unavailable();

        return $this;
    }

    /**
     * Turn every protection off, for tests that are about something else.
     */
    public function withoutProtection(): self
    {
        $this->config->set('bot-shield.enabled', false);

        return $this;
    }

    public function recorded(): CollectingEventRecorder
    {
        return $this->recorder;
    }

    public function assertBlocked(?string $form = null): self
    {
        $blocked = $this->recorder->ofType(EventType::DetectorBlock);

        Assert::assertNotEmpty($blocked, 'Expected a submission to be blocked, but none was.');

        if ($form === null) {
            return $this;
        }

        $forms = array_column($blocked, 'form');

        Assert::assertContains(
            $form,
            $forms,
            "Expected the form [{$form}] to be blocked, but it was not.",
        );

        return $this;
    }

    public function assertNotBlocked(): self
    {
        Assert::assertEmpty(
            $this->recorder->ofType(EventType::DetectorBlock),
            'Expected no submission to be blocked, but one was.',
        );

        return $this;
    }

    /**
     * A captcha verification ran for the submission, which is what being
     * challenged amounts to from the outside.
     */
    public function assertChallenged(): self
    {
        Assert::assertNotEmpty(
            $this->recorder->ofType(EventType::Captcha),
            'Expected the submission to be challenged by the captcha, but it was not.',
        );

        return $this;
    }

    public function assertNotChallenged(): self
    {
        Assert::assertEmpty(
            $this->recorder->ofType(EventType::Captcha),
            'Expected the submission not to be challenged, but the captcha ran.',
        );

        return $this;
    }

    public function assertCaptchaPassed(): self
    {
        return $this->assertCaptchaOutcome('passed');
    }

    public function assertCaptchaFailed(): self
    {
        $outcomes = array_column($this->recorder->ofType(EventType::Captcha), 'outcome');
        $failures = array_intersect($outcomes, ['failed', 'low-score', 'missing-token', 'unavailable']);

        Assert::assertNotEmpty($failures, 'Expected the captcha to reject the submission, but it did not.');

        return $this;
    }

    public function assertHoneypotTripped(): self
    {
        Assert::assertNotEmpty(
            $this->recorder->ofType(EventType::Honeypot),
            'Expected the honeypot to be tripped, but it was not.',
        );

        return $this;
    }

    public function assertRateLimited(): self
    {
        $outcomes = array_column($this->recorder->ofType(EventType::DetectorBlock), 'outcome');

        Assert::assertContains(
            'rate-limited',
            $outcomes,
            'Expected the submission to be rate limited, but it was not.',
        );

        return $this;
    }

    public function assertNothingRecorded(): self
    {
        Assert::assertEmpty($this->recorder->all(), 'Expected Bot Shield to record nothing, but it recorded events.');

        return $this;
    }

    /**
     * Calling fake() twice must not leave the fake impersonating itself.
     */
    private function configuredDriver(): string
    {
        $driver = $this->config->get('bot-shield.captcha.driver');

        if (! is_string($driver) || $driver === '' || $driver === FakeCaptchaDriver::class) {
            return CaptchaManager::GOOGLE_V3;
        }

        return $driver;
    }

    private function assertCaptchaOutcome(string $outcome): self
    {
        $outcomes = array_column($this->recorder->ofType(EventType::Captcha), 'outcome');

        Assert::assertContains(
            $outcome,
            $outcomes,
            "Expected a captcha verification with outcome [{$outcome}], but none was recorded.",
        );

        return $this;
    }
}
