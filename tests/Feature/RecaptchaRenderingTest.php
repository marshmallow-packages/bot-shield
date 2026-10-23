<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;

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

it('fetches a fresh token after each livewire round trip', function () {
    configureCaptcha('google-v3');

    $html = Blade::render('<x-bot-shield::recaptcha />');

    expect($html)->toContain("Livewire.hook('commit'")
        ->and($html)->toContain('livewire:init');
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

        $html = Blade::render('<x-bot-shield::recaptcha :hide-badge="false" />');

        /*
         * The notice exists to compensate for the hidden badge, so a tag that
         * keeps its badge visible has no reason to carry it (MM-26120).
         */
        expect($html)->not->toContain('grecaptcha-badge')
            ->and($html)->not->toContain('policies.google.com');
    });

    it('keeps the notice on such a tag when the site asks for the notice itself', function () {
        configureCaptcha('google-v3');
        config()->set('bot-shield.captcha.hide_badge', true);
        config()->set('bot-shield.captcha.show_terms', true);

        $html = Blade::render('<x-bot-shield::recaptcha :hide-badge="false" />');

        expect($html)->not->toContain('grecaptcha-badge')
            ->and($html)->toContain('policies.google.com');
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

describe('the action name', function () {
    it('passes a valid action through to the widget', function () {
        configureCaptcha('google-v3');

        expect(Blade::render('<x-bot-shield::recaptcha action="contact" />'))
            ->toContain("action: 'contact'");
    });

    it('accepts slashes and underscores, which google allows', function () {
        configureCaptcha('google-v3');

        expect(Blade::render('<x-bot-shield::recaptcha action="forms/newsletter_footer" />'))
            ->toContain("action: 'forms\\/newsletter_footer'");
    });

    it('refuses an action google would reject while developing or testing', function (string $environment) {
        configureCaptcha('google-v3');
        app()->detectEnvironment(static fn (): string => $environment);

        expect(fn () => Blade::render('<x-bot-shield::recaptcha action="newsletter-footer" />'))
            ->toThrow(ViewException::class, 'reCAPTCHA action "newsletter-footer"');
    })->with(['local', 'testing']);

    it('rewrites an action google would reject in production rather than failing silently', function () {
        configureCaptcha('google-v3');
        app()->detectEnvironment(static fn (): string => 'production');

        expect(Blade::render('<x-bot-shield::recaptcha action="newsletter-footer" />'))
            ->toContain("action: 'newsletter_footer'");
    });

    it('applies the same rule to the directive', function () {
        configureCaptcha('google-v3');
        app()->detectEnvironment(static fn (): string => 'production');

        expect(Blade::render("@botShieldRecaptcha(['action' => 'contact-form'])"))
            ->toContain("action: 'contact_form'");
    });
});

describe('placing the notice yourself', function () {
    it('renders the notice on its own', function () {
        configureCaptcha('google-v3');

        $html = Blade::render('<x-bot-shield::recaptcha-terms />');

        expect($html)->toContain('class="bot-shield-recaptcha-terms"')
            ->and($html)->toContain('policies.google.com/privacy')
            ->and($html)->toContain('policies.google.com/terms')
            ->and($html)->not->toContain('g-recaptcha-response');
    });

    it('renders the notice whatever the config says, since placing it is the intent', function () {
        configureCaptcha('google-v3');
        config()->set('bot-shield.captcha.show_terms', false);
        config()->set('bot-shield.captcha.hide_badge', false);

        expect(Blade::render('<x-bot-shield::recaptcha-terms />'))->toContain('policies.google.com/privacy');
    });

    it('renders nothing while no captcha is configured', function () {
        expect(trim(Blade::render('<x-bot-shield::recaptcha-terms />')))->toBe('');
    });

    it('renders nothing while the captcha is disabled', function () {
        configureCaptcha();
        config()->set('bot-shield.captcha.enabled', false);

        expect(trim(Blade::render('<x-bot-shield::recaptcha-terms />')))->toBe('');
    });

    it('passes a class through to the notice', function () {
        configureCaptcha('google-v3');

        expect(Blade::render('<x-bot-shield::recaptcha-terms class="text-xs text-gray-500" />'))
            ->toContain('class="bot-shield-recaptcha-terms text-xs text-gray-500"');
    });

    it('lets a tag leave the notice out so the page can place it elsewhere', function (string $tag) {
        configureCaptcha('google-v3');
        config()->set('bot-shield.captcha.hide_badge', true);

        $html = Blade::render($tag);

        expect($html)->toContain('.grecaptcha-badge')
            ->and($html)->not->toContain('policies.google.com');
    })->with([
        'string attribute' => '<x-bot-shield::recaptcha terms="false" />',
        'bound boolean' => '<x-bot-shield::recaptcha :terms="false" />',
        'directive option' => "@botShieldRecaptcha(['terms' => false])",
    ]);

    it('lets a tag ask for the notice on a site that leaves it off', function () {
        configureCaptcha('google-v3');

        expect(Blade::render('<x-bot-shield::recaptcha terms="true" />'))
            ->toContain('policies.google.com/privacy');
    });

    it('leaves the notice out of the widget on the invisible and checkbox widgets too', function (string $tag) {
        configureCaptcha('google-v2');
        config()->set('bot-shield.captcha.show_terms', true);

        expect(Blade::render($tag))->not->toContain('policies.google.com');
    })->with([
        'invisible' => '<x-bot-shield::recaptcha size="invisible" terms="false" />',
        'checkbox' => '<x-bot-shield::recaptcha terms="false" />',
    ]);
});

describe('a page with several widgets', function () {
    it('loads the google script once', function () {
        configureCaptcha('google-v3');

        $html = Blade::render('<x-bot-shield::recaptcha action="contact" /><x-bot-shield::recaptcha action="newsletter" />');

        expect(substr_count($html, 'api.js?render=test-site-key'))->toBe(1)
            ->and(substr_count($html, 'name="g-recaptcha-response"'))->toBe(2);
    });

    it('hides the badge once', function () {
        configureCaptcha('google-v3');
        config()->set('bot-shield.captcha.hide_badge', true);

        $html = Blade::render('<x-bot-shield::recaptcha action="contact" /><x-bot-shield::recaptcha action="newsletter" />');

        expect(substr_count($html, '.grecaptcha-badge'))->toBe(1);
    });

    it('loads the challenge script once as well', function () {
        configureCaptcha('google-v2');

        $html = Blade::render('<x-bot-shield::recaptcha /><x-bot-shield::recaptcha size="invisible" />');

        expect(substr_count($html, 'api.js'))->toBe(1);
    });

    it('adds the script through a guard so a livewire update cannot load it twice', function () {
        configureCaptcha('google-v3');

        $html = Blade::render('<x-bot-shield::recaptcha />');

        expect($html)->not->toContain('<script src=')
            ->and($html)->toContain("document.querySelector('script[src=");
    });
});
