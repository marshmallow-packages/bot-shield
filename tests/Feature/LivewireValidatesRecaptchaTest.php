<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Marshmallow\BotShield\Tests\Fixtures\CaptchaComponent;

beforeEach(function () {
    CaptchaComponent::resetRuns();

    configureCaptcha('google-v3');
});

it('runs the action when the provider accepts the token', function () {
    fakeSiteverify(['success' => true, 'score' => 0.9]);

    Livewire::test(CaptchaComponent::class)
        ->set('gRecaptchaResponse', 'token')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);

    expect(CaptchaComponent::$runs)->toBe(1);
});

it('refuses the action and reports a validation error when the score is too low', function () {
    fakeSiteverify(['success' => true, 'score' => 0.1]);

    Livewire::test(CaptchaComponent::class)
        ->set('gRecaptchaResponse', 'token')
        ->call('submit')
        ->assertHasErrors('g-recaptcha-response');

    expect(CaptchaComponent::$runs)->toBe(0);
});

it('refuses the action when the provider rejects the token', function () {
    fakeSiteverify(['success' => false, 'error-codes' => ['invalid-input-response']]);

    Livewire::test(CaptchaComponent::class)
        ->set('gRecaptchaResponse', 'token')
        ->call('submit')
        ->assertHasErrors('g-recaptcha-response');

    expect(CaptchaComponent::$runs)->toBe(0);
});

it('refuses an action called without ever running the client challenge', function () {
    Http::fake();

    Livewire::test(CaptchaComponent::class)
        ->call('submit')
        ->assertHasErrors('g-recaptcha-response');

    expect(CaptchaComponent::$runs)->toBe(0);

    Http::assertNothingSent();
});

it('runs the action while no captcha is configured', function () {
    config()->set('bot-shield.captcha.site_key', null);
    config()->set('bot-shield.captcha.secret_key', null);

    Http::fake();

    Livewire::test(CaptchaComponent::class)
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);

    expect(CaptchaComponent::$runs)->toBe(1);

    Http::assertNothingSent();
});

it('runs the action while the captcha is disabled', function () {
    config()->set('bot-shield.captcha.enabled', false);

    Http::fake();

    Livewire::test(CaptchaComponent::class)
        ->call('submit')
        ->assertSet('submitted', true);

    expect(CaptchaComponent::$runs)->toBe(1);
});

it('refuses the action when the provider is unreachable and failing open is off', function () {
    Http::fake(fn () => throw new ConnectionException('timed out'));

    Livewire::test(CaptchaComponent::class)
        ->set('gRecaptchaResponse', 'token')
        ->call('submit')
        ->assertHasErrors('g-recaptcha-response');

    expect(CaptchaComponent::$runs)->toBe(0);
});

it('runs the action when the provider is unreachable and failing open is on', function () {
    config()->set('bot-shield.captcha.fail_open', true);

    Http::fake(fn () => throw new ConnectionException('timed out'));

    Livewire::test(CaptchaComponent::class)
        ->set('gRecaptchaResponse', 'token')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);

    expect(CaptchaComponent::$runs)->toBe(1);
});
