<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Captcha;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Log\LogManager;
use Marshmallow\BotShield\Captcha\Drivers\GoogleV2Driver;
use Marshmallow\BotShield\Captcha\Drivers\GoogleV3Driver;
use Marshmallow\BotShield\Captcha\Drivers\NullDriver;
use Marshmallow\BotShield\Contracts\CaptchaDriver;
use Marshmallow\BotShield\Contracts\RecordsEvents;
use Marshmallow\BotShield\Enums\CaptchaOutcome;
use Marshmallow\BotShield\Enums\EventType;
use Marshmallow\BotShield\Guards\FormGuard;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class CaptchaManager
{
    public const string GOOGLE_V3 = 'google-v3';

    public const string GOOGLE_V2 = 'google-v2';

    public const string NONE = 'null';

    public function __construct(
        private readonly Repository $config,
        private readonly Container $container,
        private readonly FormGuard $guard,
        private readonly LogManager $log,
        private readonly RecordsEvents $recorder,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function verify(?string $token, ?Request $request = null, array $context = []): CaptchaVerdict
    {
        $request ??= $this->container->make(Request::class);

        if (! $this->enabled()) {
            return CaptchaVerdict::skipped();
        }

        $driver = $this->driver();

        $verdict = $driver->verify($token, $request, $this->thresholdFor($request));

        $this->record($verdict, $request, $driver, $context);

        if ($verdict->outcome !== CaptchaOutcome::Unavailable) {
            return $verdict;
        }

        if (! $this->truthy('bot-shield.captcha.fail_open')) {
            return $verdict;
        }

        return CaptchaVerdict::skipped();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function verifyRequest(Request $request, array $context = []): CaptchaVerdict
    {
        return $this->verify($this->tokenFrom($request), $request, $context);
    }

    public function driver(?string $name = null): CaptchaDriver
    {
        $name ??= (string) $this->config->get('bot-shield.captcha.driver', self::GOOGLE_V3);

        return match ($name) {
            self::GOOGLE_V3 => $this->container->make(GoogleV3Driver::class),
            self::GOOGLE_V2 => $this->container->make(GoogleV2Driver::class),
            self::NONE, '' => $this->container->make(NullDriver::class),
            default => $this->customDriver($name),
        };
    }

    /**
     * Whether a captcha will actually be verified. Blade components render
     * nothing when this is false, so a site without keys is not punished.
     */
    public function isActive(): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        return $this->driver()->isConfigured();
    }

    public function fieldName(): string
    {
        $field = $this->config->get('bot-shield.captcha.field', 'g-recaptcha-response');

        return is_string($field) && $field !== '' ? $field : 'g-recaptcha-response';
    }

    public function tokenFrom(Request $request): ?string
    {
        $token = $request->input($this->fieldName());

        return is_string($token) ? $token : null;
    }

    public function siteKey(): ?string
    {
        return $this->driver()->siteKey();
    }

    public function driverName(): string
    {
        return $this->driver()->name();
    }

    public function usesScores(): bool
    {
        return $this->driverName() === self::GOOGLE_V3;
    }

    public function showsTerms(): bool
    {
        return $this->truthy('bot-shield.captcha.show_terms');
    }

    private function thresholdFor(Request $request): ?float
    {
        if (! $this->guard->shouldChallenge($request)) {
            return null;
        }

        return (float) $this->config->get('bot-shield.captcha.score_challenge', 0.9);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function record(CaptchaVerdict $verdict, Request $request, CaptchaDriver $driver, array $context): void
    {
        $this->channel()->info('bot-shield captcha verification', array_merge([
            'driver' => $driver->name(),
            'ip' => $request->ip(),
            'url' => $request->fullUrl(),
        ], $verdict->context(), $context));

        $this->recorder->record(EventType::Captcha, $verdict->outcome->value, [
            'score' => $verdict->score,
            'form' => $this->stringOrNull($context, 'form'),
            'component' => $this->stringOrNull($context, 'component'),
            'action' => $this->stringOrNull($context, 'action'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function stringOrNull(array $context, string $key): ?string
    {
        $value = $context[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    private function channel(): LoggerInterface
    {
        $channel = $this->config->get('bot-shield.captcha.log_channel');

        if (! is_string($channel)) {
            return $this->log;
        }

        if ($channel === '') {
            return $this->log;
        }

        return $this->log->channel($channel);
    }

    private function customDriver(string $name): CaptchaDriver
    {
        if (! class_exists($name)) {
            throw new RuntimeException(sprintf(
                'Unknown captcha driver [%s]. Use [%s], [%s], [%s], or the class name of a %s implementation.',
                $name,
                self::GOOGLE_V3,
                self::GOOGLE_V2,
                self::NONE,
                CaptchaDriver::class,
            ));
        }

        $driver = $this->container->make($name);

        if (! $driver instanceof CaptchaDriver) {
            throw new RuntimeException(sprintf(
                'The captcha driver [%s] must implement %s.',
                $name,
                CaptchaDriver::class,
            ));
        }

        return $driver;
    }

    private function enabled(): bool
    {
        if (! $this->truthy('bot-shield.enabled')) {
            return false;
        }

        return $this->truthy('bot-shield.captcha.enabled');
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
