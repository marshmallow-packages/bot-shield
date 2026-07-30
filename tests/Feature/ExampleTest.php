<?php

declare(strict_types=1);

use Marshmallow\BotShield\BotShield;

it('resolves the singleton', function () {
    expect(app(BotShield::class))->toBeInstanceOf(BotShield::class);
});

it('returns the same instance from the container', function () {
    expect(app(BotShield::class))->toBe(app(BotShield::class));
});

it('merges the package config', function () {
    expect(config('bot-shield.placeholder'))->toBe('default');
});

it('loads the package translations', function () {
    expect(trans('bot-shield::messages.placeholder'))->toBe('BotShield placeholder translation.');
});

it('loads the package views', function () {
    expect(view()->exists('bot-shield::placeholder'))->toBeTrue();
});

it('registers the artisan command', function () {
    $this->artisan('bot-shield:placeholder')
        ->expectsOutputToContain('BotShield placeholder command executed.')
        ->assertSuccessful();
});
