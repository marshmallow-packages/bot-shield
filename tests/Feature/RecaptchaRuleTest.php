<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Marshmallow\BotShield\Rules\Recaptcha;
use Marshmallow\BotShield\Tests\Fixtures\ContactFormRequest;

it('passes validation when the provider accepts the token', function () {
    configureCaptcha();
    fakeSiteverify(['success' => true, 'score' => 0.9]);

    $validator = Validator::make(
        ['g-recaptcha-response' => 'token'],
        ['g-recaptcha-response' => [new Recaptcha]],
    );

    expect($validator->passes())->toBeTrue();
});

it('fails validation with a translated message when the score is too low', function () {
    configureCaptcha();
    fakeSiteverify(['success' => true, 'score' => 0.1]);

    $validator = Validator::make(
        ['g-recaptcha-response' => 'token'],
        ['g-recaptcha-response' => [new Recaptcha]],
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('g-recaptcha-response'))
        ->toBe('We could not verify that you are not a robot. Please try again.');
});

/*
 * A missing token and a rejected one read identically on purpose. The message a
 * caller gets back should not tell it which check it tripped, and under the
 * default invisible driver there is no verification for a human to "complete"
 * either.
 */
it('does not reveal whether the token was missing or rejected', function () {
    configureCaptcha();

    $messageFor = function (string $token): string {
        $validator = Validator::make(
            ['g-recaptcha-response' => $token],
            ['g-recaptcha-response' => [new Recaptcha]],
        );

        expect($validator->fails())->toBeTrue();

        return $validator->errors()->first('g-recaptcha-response');
    };

    Http::fake();
    $missing = $messageFor('');

    fakeSiteverify(['success' => false, 'error-codes' => ['invalid-input-response']]);
    $rejected = $messageFor('token');

    expect($missing)->toBe($rejected)
        ->and($missing)->not->toContain('complete');
});

it('passes validation while no captcha is configured', function () {
    Http::fake();

    $validator = Validator::make(
        ['g-recaptcha-response' => ''],
        ['g-recaptcha-response' => [new Recaptcha]],
    );

    expect($validator->passes())->toBeTrue();

    Http::assertNothingSent();
});

describe('the form request trait', function () {
    it('adds the captcha field to the rules while a captcha is active', function () {
        configureCaptcha();

        $rules = (new ContactFormRequest)->rules();

        expect($rules)->toHaveKey('g-recaptcha-response')
            ->and($rules)->toHaveKey('email')
            ->and($rules['g-recaptcha-response'][0])->toBe('required')
            ->and($rules['g-recaptcha-response'][1])->toBeInstanceOf(Recaptcha::class);
    });

    it('adds nothing while no captcha is active', function () {
        $rules = (new ContactFormRequest)->rules();

        expect($rules)->not->toHaveKey('g-recaptcha-response')
            ->and($rules)->toHaveKey('email');
    });
});
