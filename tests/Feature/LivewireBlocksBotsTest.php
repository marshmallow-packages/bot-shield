<?php

declare(strict_types=1);

use Livewire\Features\SupportAttributes\Attribute as LivewireAttribute;
use Livewire\Features\SupportAttributes\SupportAttributes;
use Livewire\Livewire;
use Marshmallow\BotShield\Tests\Fixtures\AlwaysBotDetector;
use Marshmallow\BotShield\Tests\Fixtures\GuardedComponent;
use Marshmallow\BotShield\Tests\Fixtures\ProtectedComponent;
use Symfony\Component\HttpKernel\Exception\HttpException;

/*
 * Livewire's attribute mechanism is undocumented internal API. These assertions
 * fail loudly if a Livewire upgrade moves what BotShieldAttribute depends on,
 * instead of the attributes silently never running.
 */
describe('the livewire internals we depend on', function () {
    it('still exposes the attribute base class with the members we use', function () {
        $attribute = new ReflectionClass(LivewireAttribute::class);

        expect($attribute->hasMethod('__boot'))->toBeTrue()
            ->and($attribute->hasProperty('component'))->toBeTrue()
            ->and($attribute->hasProperty('subName'))->toBeTrue()
            ->and($attribute->hasMethod('getLevel'))->toBeTrue();
    });

    it('still calls attributes through a three parameter call hook', function () {
        $call = new ReflectionMethod(SupportAttributes::class, 'call');

        expect($call->getNumberOfParameters())->toBe(3);
    });

    it('still ships the wrap and trigger helpers', function () {
        expect(function_exists('Livewire\wrap'))->toBeTrue()
            ->and(function_exists('Livewire\trigger'))->toBeTrue();
    });
});

/*
 * These drive the decision through the detector rather than a user agent:
 * Livewire's test RequestBroker does not carry a custom User-Agent into the
 * update request, so agent rules cannot be exercised from here. Rule based
 * blocking is covered in FormGuardTest.
 */
describe('the BlocksBots attribute', function () {
    beforeEach(function () {
        GuardedComponent::resetRuns();

        config()->set('bot-shield.agents.rules', []);
    });

    it('refuses a blocked request before the action runs', function () {
        config()->set('bot-shield.detector', AlwaysBotDetector::class);
        config()->set('bot-shield.forms.use_detector', true);

        Livewire::test(GuardedComponent::class)->call('submit');

        expect(GuardedComponent::$guardedRuns)->toBe(0);
    });

    it('lets an allowed request through and runs the action', function () {
        Livewire::test(GuardedComponent::class)
            ->call('submit')
            ->assertSet('submitted', true);

        expect(GuardedComponent::$guardedRuns)->toBe(1);
    });

    it('leaves actions without the attribute alone', function () {
        config()->set('bot-shield.detector', AlwaysBotDetector::class);
        config()->set('bot-shield.forms.use_detector', true);

        Livewire::test(GuardedComponent::class)
            ->call('unguardedSubmit')
            ->assertSet('submitted', true);

        expect(GuardedComponent::$unguardedRuns)->toBe(1);
    });

    it('runs the action while the form guard is disabled', function () {
        config()->set('bot-shield.detector', AlwaysBotDetector::class);
        config()->set('bot-shield.forms.use_detector', true);
        config()->set('bot-shield.forms.enabled', false);

        Livewire::test(GuardedComponent::class)
            ->call('submit')
            ->assertSet('submitted', true);

        expect(GuardedComponent::$guardedRuns)->toBe(1);
    });

    it('runs the action while the package is disabled', function () {
        config()->set('bot-shield.detector', AlwaysBotDetector::class);
        config()->set('bot-shield.forms.use_detector', true);
        config()->set('bot-shield.enabled', false);

        Livewire::test(GuardedComponent::class)
            ->call('submit')
            ->assertSet('submitted', true);

        expect(GuardedComponent::$guardedRuns)->toBe(1);
    });
});

describe('the ProtectsAgainstBots trait', function () {
    it('refuses a blocked request', function () {
        withUserAgent('curl/8.7.1');

        expect(fn () => (new ProtectedComponent)->submit())
            ->toThrow(HttpException::class, 'This request was blocked.');
    });

    it('lets an allowed request through', function () {
        withUserAgent('Mozilla/5.0 (Macintosh) Safari/537.36');

        $component = new ProtectedComponent;
        $component->submit();

        expect($component->submitted)->toBeTrue();
    });
});
