<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Marshmallow\BotShield\Captcha\CaptchaManager;
use Marshmallow\BotShield\Enums\EventType;
use Marshmallow\BotShield\Facades\BotShield;
use Marshmallow\BotShield\Guards\FormGuard;
use Marshmallow\BotShield\Guards\SubmissionLimiter;
use Marshmallow\BotShield\Models\BotShieldEvent;
use PHPUnit\Framework\ExpectationFailedException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

function fakedRequest(string $userAgent = 'Mozilla/5.0 (Macintosh) Safari/537.36'): Request
{
    return Request::create('/contact', 'POST', [], [], [], ['HTTP_USER_AGENT' => $userAgent]);
}

it('scripts a passing captcha without reaching the provider', function () {
    $fake = BotShield::fake()->captchaPasses(0.95);

    Http::fake();

    $verdict = app(CaptchaManager::class)->verify('token', fakedRequest());

    expect($verdict->passes())->toBeTrue()
        ->and($verdict->score)->toBe(0.95);

    Http::assertNothingSent();

    $fake->assertChallenged()->assertCaptchaPassed();
});

it('scripts a failing captcha', function () {
    $fake = BotShield::fake()->captchaFails();

    $verdict = app(CaptchaManager::class)->verify('token', fakedRequest());

    expect($verdict->fails())->toBeTrue();

    $fake->assertCaptchaFailed();
});

it('scripts a low score', function () {
    $fake = BotShield::fake()->captchaScores(0.1);

    $verdict = app(CaptchaManager::class)->verify('token', fakedRequest());

    expect($verdict->fails())->toBeTrue()
        ->and($verdict->score)->toBe(0.1);

    $fake->assertCaptchaFailed();
});

it('scripts an unreachable provider', function () {
    $fake = BotShield::fake()->captchaUnavailable();

    expect(app(CaptchaManager::class)->verify('token', fakedRequest())->fails())->toBeTrue();

    $fake->assertCaptchaFailed();
});

it('still refuses a blank token, because that is real behaviour and not a stub', function () {
    $fake = BotShield::fake()->captchaPasses();

    expect(app(CaptchaManager::class)->verify('', fakedRequest())->fails())->toBeTrue();

    $fake->assertCaptchaFailed();
});

it('asserts a blocked submission', function () {
    $fake = BotShield::fake();

    expect(fn () => app(FormGuard::class)->guard(fakedRequest('curl/8.7.1'), 'contact'))
        ->toThrow(HttpException::class);

    $fake->assertBlocked()->assertBlocked('contact');
});

it('fails the blocked assertion when nothing was blocked', function () {
    $fake = BotShield::fake();

    expect(fn () => $fake->assertBlocked())
        ->toThrow(ExpectationFailedException::class, 'Expected a submission to be blocked');
});

it('fails the blocked assertion when a different form was blocked', function () {
    $fake = BotShield::fake();

    try {
        app(FormGuard::class)->guard(fakedRequest('curl/8.7.1'), 'newsletter');
    } catch (HttpException) {
        //
    }

    expect(fn () => $fake->assertBlocked('contact'))
        ->toThrow(ExpectationFailedException::class, 'Expected the form [contact] to be blocked');
});

it('asserts nothing was blocked', function () {
    $fake = BotShield::fake();

    app(FormGuard::class)->guard(fakedRequest(), 'contact');

    $fake->assertNotBlocked();
});

it('asserts a rate limited submission', function () {
    config()->set('bot-shield.rate_limit.attempts', 1);

    $fake = BotShield::fake();
    $limiter = app(SubmissionLimiter::class);
    $request = fakedRequest();

    $limiter->hit($request, 'contact');

    try {
        $limiter->hit($request, 'contact');
    } catch (TooManyRequestsHttpException) {
        //
    }

    $fake->assertRateLimited();
});

it('keeps events in memory instead of the database', function () {
    $fake = BotShield::fake();

    try {
        app(FormGuard::class)->guard(fakedRequest('curl/8.7.1'), 'contact');
    } catch (HttpException) {
        //
    }

    expect($fake->recorded()->all())->toHaveCount(1)
        ->and(BotShieldEvent::query()->count())->toBe(0);
});

it('asserts nothing was recorded at all', function () {
    BotShield::fake()->assertNothingRecorded();
});

it('turns every protection off on request', function () {
    $fake = BotShield::fake()->withoutProtection();

    app(FormGuard::class)->guard(fakedRequest('curl/8.7.1'), 'contact');

    expect(app(CaptchaManager::class)->verify('', fakedRequest())->passes())->toBeTrue();

    $fake->assertNotBlocked()->assertNothingRecorded();
});

it('still answers isBot through the real detector', function () {
    BotShield::fake();

    expect(BotShield::isBot(fakedRequest('curl/8.7.1')))->toBeTrue()
        ->and(BotShield::isBot(fakedRequest()))->toBeFalse();
});

it('asserts a tripped honeypot', function () {
    $fake = BotShield::fake();

    $fake->recorded()->record(EventType::Honeypot, 'tripped');

    $fake->assertHoneypotTripped();
});

describe('the driver the fake stands in for', function () {
    it('renders the widget the application actually ships', function () {
        config()->set('bot-shield.captcha.driver', 'google-v3');

        BotShield::fake();

        $html = Blade::render('<x-bot-shield::recaptcha />');

        expect(app(CaptchaManager::class)->usesScores())->toBeTrue()
            ->and($html)->toContain('grecaptcha.execute')
            ->and($html)->not->toContain('class="g-recaptcha"');
    });

    it('follows a site onto the challenge driver', function () {
        config()->set('bot-shield.captcha.driver', 'google-v2');

        BotShield::fake();

        expect(app(CaptchaManager::class)->usesScores())->toBeFalse()
            ->and(Blade::render('<x-bot-shield::recaptcha />'))->toContain('class="g-recaptcha"');
    });

    it('takes an explicit driver when the test is about that driver', function () {
        config()->set('bot-shield.captcha.driver', 'google-v3');

        BotShield::fake('google-v2');

        expect(app(CaptchaManager::class)->usesScores())->toBeFalse();
    });

    it('survives being faked twice', function () {
        config()->set('bot-shield.captcha.driver', 'google-v3');

        BotShield::fake();
        BotShield::fake();

        expect(app(CaptchaManager::class)->driverName())->toBe('google-v3');
    });
});
