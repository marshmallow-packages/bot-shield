<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Livewire\Livewire;
use Marshmallow\BotShield\Enums\EventType;
use Marshmallow\BotShield\Guards\SubmissionLimiter;
use Marshmallow\BotShield\Models\BotShieldEvent;
use Marshmallow\BotShield\Tests\Fixtures\ThrottledComponent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

function submissionFrom(string $ip = '203.0.113.7'): Request
{
    return Request::create('/contact', 'POST', [], [], [], [
        'REMOTE_ADDR' => $ip,
        'HTTP_USER_AGENT' => 'Mozilla/5.0 (Macintosh) Safari/537.36',
    ]);
}

it('allows submissions up to the configured limit', function () {
    config()->set('bot-shield.rate_limit.attempts', 3);

    $limiter = app(SubmissionLimiter::class);
    $request = submissionFrom();

    foreach (range(1, 3) as $ignored) {
        $limiter->hit($request, 'contact');
    }
})->throwsNoExceptions();

it('refuses the submission past the limit', function () {
    config()->set('bot-shield.rate_limit.attempts', 3);

    $limiter = app(SubmissionLimiter::class);
    $request = submissionFrom();

    foreach (range(1, 3) as $ignored) {
        $limiter->hit($request, 'contact');
    }

    expect(fn () => $limiter->hit($request, 'contact'))
        ->toThrow(TooManyRequestsHttpException::class, 'Too many submissions.');
});

it('counts each form separately', function () {
    config()->set('bot-shield.rate_limit.attempts', 1);

    $limiter = app(SubmissionLimiter::class);
    $request = submissionFrom();

    $limiter->hit($request, 'contact');
    $limiter->hit($request, 'newsletter');

    expect(fn () => $limiter->hit($request, 'contact'))->toThrow(TooManyRequestsHttpException::class);
});

it('counts each address separately', function () {
    config()->set('bot-shield.rate_limit.attempts', 1);

    $limiter = app(SubmissionLimiter::class);

    $limiter->hit(submissionFrom('203.0.113.7'), 'contact');
    $limiter->hit(submissionFrom('203.0.113.8'), 'contact');

    expect(fn () => $limiter->hit(submissionFrom('203.0.113.7'), 'contact'))
        ->toThrow(TooManyRequestsHttpException::class);
});

it('records the refusal', function () {
    config()->set('bot-shield.rate_limit.attempts', 1);

    $limiter = app(SubmissionLimiter::class);
    $request = submissionFrom();

    $limiter->hit($request, 'contact');

    try {
        $limiter->hit($request, 'contact');
    } catch (TooManyRequestsHttpException) {
        //
    }

    expect(BotShieldEvent::query()->sole())
        ->type->toBe(EventType::DetectorBlock)
        ->outcome->toBe('rate-limited')
        ->form->toBe('contact');
});

it('can be cleared', function () {
    config()->set('bot-shield.rate_limit.attempts', 1);

    $limiter = app(SubmissionLimiter::class);
    $request = submissionFrom();

    $limiter->hit($request, 'contact');
    $limiter->clear($request, 'contact');
    $limiter->hit($request, 'contact');
})->throwsNoExceptions();

it('does nothing while disabled', function () {
    config()->set('bot-shield.rate_limit.enabled', false);
    config()->set('bot-shield.rate_limit.attempts', 1);

    $limiter = app(SubmissionLimiter::class);
    $request = submissionFrom();

    foreach (range(1, 20) as $ignored) {
        $limiter->hit($request, 'contact');
    }
})->throwsNoExceptions();

it('does nothing while the package is disabled', function () {
    config()->set('bot-shield.enabled', false);
    config()->set('bot-shield.rate_limit.attempts', 1);

    $limiter = app(SubmissionLimiter::class);
    $request = submissionFrom();

    $limiter->hit($request, 'contact');
    $limiter->hit($request, 'contact');
})->throwsNoExceptions();

describe('the RateLimitsSubmissions attribute', function () {
    beforeEach(fn () => ThrottledComponent::resetRuns());

    it('lets the action run inside the limit', function () {
        Livewire::test(ThrottledComponent::class)->call('submit');

        expect(ThrottledComponent::$runs)->toBe(1);
    });

    it('stops the action past the limit', function () {
        $component = Livewire::test(ThrottledComponent::class);

        $component->call('submit');
        $component->call('submit');
        $component->call('submit');

        expect(ThrottledComponent::$runs)->toBe(2);
    });
});
