<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Marshmallow\BotShield\Captcha\CaptchaManager;
use Marshmallow\BotShield\Enums\CaptchaOutcome;

function captchaRequest(string $userAgent = 'Mozilla/5.0 (Macintosh) Safari/537.36'): Request
{
    return Request::create('/contact', 'POST', [], [], [], ['HTTP_USER_AGENT' => $userAgent]);
}

describe('the score based driver', function () {
    beforeEach(fn () => configureCaptcha('google-v3'));

    it('passes a token scoring at or above the threshold', function (float $score) {
        fakeSiteverify(['success' => true, 'score' => $score]);

        $verdict = app(CaptchaManager::class)->verify('token', captchaRequest());

        expect($verdict->passes())->toBeTrue()
            ->and($verdict->outcome)->toBe(CaptchaOutcome::Passed)
            ->and($verdict->score)->toBe($score);
    })->with([0.6, 0.7, 0.9, 1.0]);

    it('refuses a token scoring below the threshold', function () {
        fakeSiteverify(['success' => true, 'score' => 0.3]);

        $verdict = app(CaptchaManager::class)->verify('token', captchaRequest());

        expect($verdict->fails())->toBeTrue()
            ->and($verdict->outcome)->toBe(CaptchaOutcome::LowScore)
            ->and($verdict->score)->toBe(0.3)
            ->and($verdict->threshold)->toBe(0.6);
    });

    it('honours a configured threshold', function () {
        config()->set('bot-shield.captcha.score', 0.2);

        fakeSiteverify(['success' => true, 'score' => 0.3]);

        expect(app(CaptchaManager::class)->verify('token', captchaRequest())->passes())->toBeTrue();
    });

    it('applies the stricter threshold to agents matched by a challenge rule', function () {
        config()->set('bot-shield.agents.rules', [
            ['pattern' => 'SuspiciousClient', 'action' => 'challenge'],
        ]);

        fakeSiteverify(['success' => true, 'score' => 0.7]);

        $verdict = app(CaptchaManager::class)->verify('token', captchaRequest('SuspiciousClient/1.0'));

        expect($verdict->outcome)->toBe(CaptchaOutcome::LowScore)
            ->and($verdict->threshold)->toBe(0.9);
    });

    it('refuses a token the provider rejects', function () {
        fakeSiteverify(['success' => false, 'error-codes' => ['invalid-input-response']]);

        $verdict = app(CaptchaManager::class)->verify('token', captchaRequest());

        expect($verdict->outcome)->toBe(CaptchaOutcome::Failed)
            ->and($verdict->errorCodes)->toBe(['invalid-input-response']);
    });

    it('treats a response without a score as the lowest score', function () {
        fakeSiteverify(['success' => true]);

        expect(app(CaptchaManager::class)->verify('token', captchaRequest())->outcome)
            ->toBe(CaptchaOutcome::LowScore);
    });

    it('can be told not to log, while still recording the event', function () {
        Log::spy();

        config()->set('bot-shield.captcha.log', false);

        fakeSiteverify(['success' => true, 'score' => 0.8]);

        $verdict = app(CaptchaManager::class)->verify('token', captchaRequest());

        expect($verdict->passes())->toBeTrue();

        Log::shouldNotHaveReceived('info');
    });

    it('logs to a named channel when one is configured', function () {
        Log::shouldReceive('channel')->with('stack')->once()->andReturnSelf();
        Log::shouldReceive('info')->once();

        config()->set('bot-shield.captcha.log_channel', 'stack');

        fakeSiteverify(['success' => true, 'score' => 0.8]);

        app(CaptchaManager::class)->verify('token', captchaRequest());
    });

    it('logs every verification with its score', function () {
        Log::spy();

        fakeSiteverify(['success' => true, 'score' => 0.8]);

        app(CaptchaManager::class)->verify('token', captchaRequest(), ['form' => 'contact']);

        Log::shouldHaveReceived('info')
            ->withArgs(function (string $message, array $context): bool {
                return $context['outcome'] === 'passed'
                    && $context['score'] === 0.8
                    && $context['driver'] === 'google-v3'
                    && $context['form'] === 'contact';
            })
            ->once();
    });
});

describe('the challenge based driver', function () {
    beforeEach(fn () => configureCaptcha('google-v2'));

    it('passes a successful verification without needing a score', function () {
        fakeSiteverify(['success' => true]);

        $verdict = app(CaptchaManager::class)->verify('token', captchaRequest());

        expect($verdict->passes())->toBeTrue()
            ->and($verdict->score)->toBeNull();
    });

    it('refuses a failed verification', function () {
        fakeSiteverify(['success' => false, 'error-codes' => ['timeout-or-duplicate']]);

        expect(app(CaptchaManager::class)->verify('token', captchaRequest())->outcome)
            ->toBe(CaptchaOutcome::Failed);
    });
});

describe('tokens that never reach the provider', function () {
    it('refuses a blank token, because a public livewire method can skip the challenge', function (?string $token) {
        configureCaptcha();

        Http::fake();

        $verdict = app(CaptchaManager::class)->verify($token, captchaRequest());

        expect($verdict->outcome)->toBe(CaptchaOutcome::MissingToken);

        Http::assertNothingSent();
    })->with([null, '', '   ']);
});

describe('when no captcha is active', function () {
    it('skips verification while the keys are missing', function () {
        config()->set('bot-shield.captcha.driver', 'google-v3');

        Http::fake();

        $verdict = app(CaptchaManager::class)->verify('token', captchaRequest());

        expect($verdict->outcome)->toBe(CaptchaOutcome::Skipped)
            ->and($verdict->passes())->toBeTrue();

        Http::assertNothingSent();
    });

    it('skips verification while the captcha is disabled', function () {
        configureCaptcha();
        config()->set('bot-shield.captcha.enabled', false);

        Http::fake();

        expect(app(CaptchaManager::class)->verify('token', captchaRequest())->outcome)
            ->toBe(CaptchaOutcome::Skipped);

        Http::assertNothingSent();
    });

    it('skips verification while the package is disabled', function () {
        configureCaptcha();
        config()->set('bot-shield.enabled', false);

        Http::fake();

        expect(app(CaptchaManager::class)->verify('token', captchaRequest())->outcome)
            ->toBe(CaptchaOutcome::Skipped);
    });

    it('skips verification on the null driver', function () {
        configureCaptcha('null');

        Http::fake();

        expect(app(CaptchaManager::class)->verify('token', captchaRequest())->outcome)
            ->toBe(CaptchaOutcome::Skipped);

        Http::assertNothingSent();
    });

    it('reports that no captcha is active', function () {
        expect(app(CaptchaManager::class)->isActive())->toBeFalse();

        configureCaptcha();

        expect(app(CaptchaManager::class)->isActive())->toBeTrue();
    });
});

describe('when the provider cannot be reached', function () {
    beforeEach(fn () => configureCaptcha('google-v3'));

    it('refuses the submission by default', function () {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out'));

        $verdict = app(CaptchaManager::class)->verify('token', captchaRequest());

        expect($verdict->outcome)->toBe(CaptchaOutcome::Unavailable)
            ->and($verdict->fails())->toBeTrue();
    });

    it('lets the submission through when configured to fail open', function () {
        config()->set('bot-shield.captcha.fail_open', true);

        Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out'));

        expect(app(CaptchaManager::class)->verify('token', captchaRequest())->passes())->toBeTrue();
    });

    it('still logs the outage before failing open', function () {
        Log::spy();

        config()->set('bot-shield.captcha.fail_open', true);

        Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out'));

        app(CaptchaManager::class)->verify('token', captchaRequest());

        Log::shouldHaveReceived('info')
            ->withArgs(fn (string $message, array $context): bool => $context['outcome'] === 'unavailable')
            ->once();
    });

    it('treats a server error response as unavailable', function () {
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response('', 500),
        ]);

        expect(app(CaptchaManager::class)->verify('token', captchaRequest())->outcome)
            ->toBe(CaptchaOutcome::Unavailable);
    });
});

describe('driver resolution', function () {
    it('rejects an unknown driver with a helpful message', function () {
        config()->set('bot-shield.captcha.driver', 'hcaptcha');

        expect(fn () => app(CaptchaManager::class)->driver())
            ->toThrow(RuntimeException::class, 'Unknown captcha driver [hcaptcha]');
    });

    it('rejects a class that is not a captcha driver', function () {
        config()->set('bot-shield.captcha.driver', stdClass::class);

        expect(fn () => app(CaptchaManager::class)->driver())
            ->toThrow(RuntimeException::class, 'must implement');
    });

    it('reads the token out of the request payload', function () {
        configureCaptcha();

        $request = Request::create('/contact', 'POST', ['g-recaptcha-response' => 'from-payload']);

        expect(app(CaptchaManager::class)->tokenFrom($request))->toBe('from-payload');
    });
});
