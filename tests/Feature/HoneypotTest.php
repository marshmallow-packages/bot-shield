<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Marshmallow\BotShield\Honeypot\SpatieHoneypotConfigurator;
use Marshmallow\BotShield\Tests\Fixtures\SpamProtectedComponent;
use Marshmallow\BotShield\Tests\Fixtures\UnprotectedSpamComponent;
use Spatie\Honeypot\Events\SpamDetectedEvent;
use Spatie\Honeypot\Http\Livewire\Concerns\HoneypotData;
use Symfony\Component\HttpKernel\Exception\HttpException;

function spamProtectedComponent(): SpamProtectedComponent
{
    $component = new SpamProtectedComponent;
    $component->mount();

    return $component;
}

describe('the honeypot component', function () {
    it('renders the hidden fields', function () {
        $html = Blade::render('<x-bot-shield::honeypot />');

        expect($html)->toContain('style="display: none"')
            ->and($html)->toContain('aria-hidden="true"')
            ->and($html)->toContain('contact_reference')
            ->and($html)->toContain('submitted_from');
    });

    it('renders nothing while the honeypot is disabled', function () {
        config()->set('bot-shield.honeypot.enabled', false);

        expect(trim(Blade::render('<x-bot-shield::honeypot />')))->toBe('');
    });

    it('renders nothing while the package is disabled', function () {
        config()->set('bot-shield.enabled', false);

        expect(trim(Blade::render('<x-bot-shield::honeypot />')))->toBe('');
    });

    it('binds the fields to a livewire property when one is named', function () {
        $html = Blade::render('<x-bot-shield::honeypot livewire-model="honeypotData" />');

        expect($html)->toContain('wire:model')
            ->and($html)->toContain('honeypotData.contact_reference');
    });

    it('leaves out the livewire binding when no property is named', function () {
        expect(Blade::render('<x-bot-shield::honeypot />'))->not->toContain('wire:model');
    });

    it('randomizes the field name so it cannot be matched on sight', function () {
        $first = Blade::render('<x-bot-shield::honeypot />');
        $second = Blade::render('<x-bot-shield::honeypot />');

        expect($first)->not->toBe($second);
    });

    it('keeps the field name stable when randomizing is off', function () {
        config()->set('honeypot.randomize_name_field_name', false);

        expect(Blade::render('<x-bot-shield::honeypot />'))
            ->toContain('name="contact_reference"');
    });
});

describe('config unification', function () {
    it('drives spatie config from this package', function () {
        expect(config('honeypot.name_field_name'))->toBe('contact_reference')
            ->and(config('honeypot.valid_from_field_name'))->toBe('submitted_from')
            ->and(config('honeypot.amount_of_seconds'))->toBe(2)
            ->and(config('honeypot.randomize_name_field_name'))->toBeTrue()
            ->and(config('honeypot.enabled'))->toBeTrue();
    });

    it('leaves spatie config alone when asked not to manage it', function () {
        config()->set('bot-shield.honeypot.manage_spatie_config', false);
        config()->set('honeypot.name_field_name', 'kept_by_hand');
        config()->set('honeypot.amount_of_seconds', 9);

        app(SpatieHoneypotConfigurator::class)->apply();

        expect(config('honeypot.name_field_name'))->toBe('kept_by_hand')
            ->and(config('honeypot.amount_of_seconds'))->toBe(9);
    });

    it('applies this package values when managing is on', function () {
        config()->set('honeypot.name_field_name', 'stale');
        config()->set('bot-shield.honeypot.name_field', 'fresh_field');
        config()->set('bot-shield.honeypot.seconds', 7);

        app(SpatieHoneypotConfigurator::class)->apply();

        expect(config('honeypot.name_field_name'))->toBe('fresh_field')
            ->and(config('honeypot.amount_of_seconds'))->toBe(7);
    });
});

describe('the spam protection trait', function () {
    beforeEach(fn () => SpamProtectedComponent::resetRuns());

    it('lets a clean submission through once the minimum time has passed', function () {
        $component = spamProtectedComponent();

        $this->travel(5)->seconds();

        $component->submit();

        expect($component->submitted)->toBeTrue()
            ->and(SpamProtectedComponent::$runs)->toBe(1);
    });

    it('refuses a submission that filled the hidden field', function () {
        Event::fake([SpamDetectedEvent::class]);

        $component = spamProtectedComponent();
        $component->honeypotData->contact_reference = 'a bot wrote here';

        $this->travel(5)->seconds();

        expect(fn () => $component->submit())
            ->toThrow(HttpException::class, 'This submission was rejected as spam.');

        expect(SpamProtectedComponent::$runs)->toBe(0);

        Event::assertDispatched(SpamDetectedEvent::class);
    });

    it('refuses a submission that arrived faster than a person could type', function () {
        $component = spamProtectedComponent();

        expect(fn () => $component->submit())->toThrow(HttpException::class);

        expect(SpamProtectedComponent::$runs)->toBe(0);
    });

    it('honours a configured status', function () {
        config()->set('bot-shield.honeypot.status', 404);

        $component = spamProtectedComponent();

        try {
            $component->submit();
        } catch (HttpException $exception) {
            expect($exception->getStatusCode())->toBe(404);

            return;
        }

        $this->fail('The guard did not refuse the submission.');
    });

    it('does nothing while the honeypot is disabled', function () {
        config()->set('bot-shield.honeypot.enabled', false);

        $component = spamProtectedComponent();
        $component->honeypotData->contact_reference = 'a bot wrote here';

        $component->submit();

        expect($component->submitted)->toBeTrue();
    });

    it('explains what is missing when the component has no honeypot data', function () {
        expect(fn () => (new UnprotectedSpamComponent)->submit())
            ->toThrow(RuntimeException::class, 'needs a public');
    });

    it('carries the honeypot data across a livewire round trip', function () {
        expect(new HoneypotData)->toBeInstanceOf(HoneypotData::class)
            ->and((new HoneypotData)->toLivewire())->toHaveKey('contact_reference');
    });
});
