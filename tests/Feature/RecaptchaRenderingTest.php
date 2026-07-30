<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

it('renders nothing while no captcha is configured', function () {
    expect(trim(Blade::render('<x-bot-shield::recaptcha />')))->toBe('');
});

it('renders nothing while the captcha is disabled', function () {
    configureCaptcha();
    config()->set('bot-shield.captcha.enabled', false);

    expect(trim(Blade::render('<x-bot-shield::recaptcha />')))->toBe('');
});

it('renders the score based widget with the site key', function () {
    configureCaptcha('google-v3');

    $html = Blade::render('<x-bot-shield::recaptcha />');

    expect($html)->toContain('test-site-key')
        ->and($html)->toContain('api.js?render=test-site-key')
        ->and($html)->toContain('grecaptcha.execute')
        ->and($html)->toContain('name="g-recaptcha-response"');
});

it('renders the checkbox widget for the challenge driver', function () {
    configureCaptcha('google-v2');

    $html = Blade::render('<x-bot-shield::recaptcha />');

    expect($html)->toContain('class="g-recaptcha"')
        ->and($html)->toContain('data-sitekey="test-site-key"')
        ->and($html)->toContain('data-size="normal"')
        ->and($html)->not->toContain('grecaptcha.execute');
});

it('renders the invisible widget when asked for that size', function () {
    configureCaptcha('google-v2');

    $html = Blade::render('<x-bot-shield::recaptcha size="invisible" />');

    expect($html)->toContain('data-size="invisible"')
        ->and($html)->toContain('grecaptcha.execute');
});

it('takes the widget size from config when the tag does not override it', function () {
    configureCaptcha('google-v2');
    config()->set('bot-shield.captcha.size', 'invisible');

    expect(Blade::render('<x-bot-shield::recaptcha />'))->toContain('data-size="invisible"');
});

it('passes the theme and locale through to the widget', function () {
    configureCaptcha('google-v2');

    $html = Blade::render('<x-bot-shield::recaptcha theme="dark" locale="nl" />');

    expect($html)->toContain('data-theme="dark"')
        ->and($html)->toContain('hl=nl');
});

it('binds the token to a livewire property when one is named', function () {
    configureCaptcha('google-v3');

    $html = Blade::render('<x-bot-shield::recaptcha property="gRecaptchaResponse" />');

    expect($html)->toContain('wire:model="gRecaptchaResponse"');
});

it('omits the livewire binding when no property is named', function () {
    configureCaptcha('google-v3');

    expect(Blade::render('<x-bot-shield::recaptcha />'))->not->toContain('wire:model');
});

it('renders the google terms notice only when enabled', function () {
    configureCaptcha('google-v3');

    expect(Blade::render('<x-bot-shield::recaptcha />'))->not->toContain('policies.google.com');

    config()->set('bot-shield.captcha.show_terms', true);

    $html = Blade::render('<x-bot-shield::recaptcha />');

    expect($html)->toContain('policies.google.com/privacy')
        ->and($html)->toContain('policies.google.com/terms')
        ->and($html)->toContain('protected by reCAPTCHA');
});

describe('moving the badge', function () {
    it('leaves the invisible widget in the bottom right by default', function () {
        configureCaptcha('google-v2');

        expect(Blade::render('<x-bot-shield::recaptcha size="invisible" />'))
            ->toContain('data-badge="bottomright"');
    });

    it('places it where config asks', function () {
        configureCaptcha('google-v2');
        config()->set('bot-shield.captcha.badge', 'inline');

        expect(Blade::render('<x-bot-shield::recaptcha size="invisible" />'))
            ->toContain('data-badge="inline"');
    });

    it('lets a tag override the position', function () {
        configureCaptcha('google-v2');

        expect(Blade::render('<x-bot-shield::recaptcha size="invisible" badge="bottomleft" />'))
            ->toContain('data-badge="bottomleft"');
    });
});

describe('hiding the badge', function () {
    it('leaves the badge alone by default', function () {
        configureCaptcha('google-v3');

        expect(Blade::render('<x-bot-shield::recaptcha />'))->not->toContain('grecaptcha-badge');
    });

    it('hides the badge and shows the notice when enabled in config', function () {
        configureCaptcha('google-v3');
        config()->set('bot-shield.captcha.hide_badge', true);

        $html = Blade::render('<x-bot-shield::recaptcha />');

        /*
         * The !important is asserted deliberately: Google sets visibility on the
         * badge inline, so a rule without it is silently ignored.
         */
        expect($html)->toContain('.grecaptcha-badge')
            ->and($html)->toContain('visibility: hidden !important')
            ->and($html)->not->toContain('display: none')
            ->and($html)->toContain('policies.google.com/privacy');
    });

    it('hides the badge on the invisible widget too', function () {
        configureCaptcha('google-v2');
        config()->set('bot-shield.captcha.hide_badge', true);

        expect(Blade::render('<x-bot-shield::recaptcha size="invisible" />'))
            ->toContain('.grecaptcha-badge');
    });

    it('hides the badge for a single tag on a site that leaves it visible', function (string $tag) {
        configureCaptcha('google-v3');

        $html = Blade::render($tag);

        expect($html)->toContain('.grecaptcha-badge')
            ->and($html)->toContain('policies.google.com/privacy');
    })->with([
        'string attribute' => '<x-bot-shield::recaptcha hide-badge="true" />',
        'bound boolean' => '<x-bot-shield::recaptcha :hide-badge="true" />',
        'directive option' => "@botShieldRecaptcha(['hideBadge' => true])",
    ]);

    it('keeps the badge for a single tag on a site that hides it', function () {
        configureCaptcha('google-v3');
        config()->set('bot-shield.captcha.hide_badge', true);

        expect(Blade::render('<x-bot-shield::recaptcha :hide-badge="false" />'))
            ->not->toContain('grecaptcha-badge');
    });
});

describe('the blade directive', function () {
    it('renders the same widget as the component', function () {
        configureCaptcha('google-v3');

        expect(Blade::render('@botShieldRecaptcha'))->toContain('api.js?render=test-site-key');
    });

    it('accepts options', function () {
        configureCaptcha('google-v2');

        expect(Blade::render("@botShieldRecaptcha(['size' => 'invisible', 'theme' => 'dark'])"))
            ->toContain('data-size="invisible"')
            ->toContain('data-theme="dark"');
    });

    it('renders nothing while no captcha is configured', function () {
        expect(trim(Blade::render('@botShieldRecaptcha')))->toBe('');
    });
});
