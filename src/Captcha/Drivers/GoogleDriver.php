<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Captcha\Drivers;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
use Marshmallow\BotShield\Captcha\CaptchaVerdict;
use Marshmallow\BotShield\Contracts\CaptchaDriver;
use Marshmallow\BotShield\Enums\CaptchaOutcome;
use Throwable;

abstract class GoogleDriver implements CaptchaDriver
{
    protected const string VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    public function __construct(
        protected readonly Repository $config,
        protected readonly HttpFactory $http,
    ) {}

    public function verify(?string $token, Request $request, ?float $threshold = null): CaptchaVerdict
    {
        if (! $this->isConfigured()) {
            return CaptchaVerdict::skipped();
        }

        /*
         * A public Livewire method can be called without ever running the
         * client side challenge, so a blank token is refused rather than
         * treated as "nothing to check".
         */
        if (blank($token)) {
            return new CaptchaVerdict(CaptchaOutcome::MissingToken);
        }

        try {
            $response = $this->http
                ->asForm()
                ->timeout($this->timeout())
                ->connectTimeout($this->connectTimeout())
                ->post(self::VERIFY_URL, [
                    'secret' => $this->secretKey(),
                    'response' => $token,
                    'remoteip' => $request->ip(),
                ]);
        } catch (ConnectionException $exception) {
            $this->reportOutage($exception);

            return new CaptchaVerdict(CaptchaOutcome::Unavailable);
        }

        if ($response->failed()) {
            $this->reportOutage($response->toException());

            return new CaptchaVerdict(CaptchaOutcome::Unavailable);
        }

        return $this->verdictFrom((array) $response->json(), $threshold);
    }

    public function isConfigured(): bool
    {
        if (blank($this->siteKey())) {
            return false;
        }

        return filled($this->secretKey());
    }

    public function siteKey(): ?string
    {
        $siteKey = $this->config->get('bot-shield.captcha.site_key');

        return is_string($siteKey) ? $siteKey : null;
    }

    /**
     * @param  array<mixed, mixed>  $payload
     */
    abstract protected function verdictFrom(array $payload, ?float $threshold): CaptchaVerdict;

    /**
     * @param  array<mixed, mixed>  $payload
     * @return list<string>
     */
    protected function errorCodes(array $payload): array
    {
        return array_values(array_filter((array) ($payload['error-codes'] ?? []), is_string(...)));
    }

    /**
     * @param  array<mixed, mixed>  $payload
     */
    protected function succeeded(array $payload): bool
    {
        return ($payload['success'] ?? false) === true;
    }

    /**
     * An unreachable provider refuses every submission, so it is worth knowing
     * about. It is opt-in because a real outage means one report per attempt
     * across every form, which is the kind of flood this package exists to
     * prevent. Sites that had a report() in their own captcha code before
     * adopting this one should turn it on to keep that signal.
     */
    protected function reportOutage(?Throwable $exception): void
    {
        if ($exception === null) {
            return;
        }

        $report = filter_var(
            $this->config->get('bot-shield.captcha.report_outages', false),
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE,
        ) === true;

        if (! $report) {
            return;
        }

        report($exception);
    }

    protected function secretKey(): ?string
    {
        $secretKey = $this->config->get('bot-shield.captcha.secret_key');

        return is_string($secretKey) ? $secretKey : null;
    }

    protected function timeout(): int
    {
        return (int) $this->config->get('bot-shield.captcha.timeout', 5);
    }

    protected function connectTimeout(): int
    {
        return (int) $this->config->get('bot-shield.captcha.connect_timeout', 3);
    }
}
