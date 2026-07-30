<?php

declare(strict_types=1);

use Marshmallow\BotShield\Hardening\ExceptionRule;

it('matches on the exception class alone when no needles are configured', function () {
    $rule = ExceptionRule::fromArray(['class' => RuntimeException::class]);

    expect($rule->matches(new RuntimeException('anything at all')))->toBeTrue();
});

it('matches subclasses of the configured class', function () {
    $rule = ExceptionRule::fromArray(['class' => Exception::class]);

    expect($rule->matches(new RuntimeException('boom')))->toBeTrue();
});

it('does not match a different exception class', function () {
    $rule = ExceptionRule::fromArray(['class' => TypeError::class]);

    expect($rule->matches(new RuntimeException('boom')))->toBeFalse();
});

it('requires every configured needle to be present', function () {
    $rule = ExceptionRule::fromArray([
        'class' => TypeError::class,
        'contains' => ['Cannot assign', 'to property'],
    ]);

    expect($rule->matches(new TypeError('Cannot assign array to property Foo::$bar of type string')))->toBeTrue()
        ->and($rule->matches(new TypeError('Cannot assign array to something else')))->toBeFalse();
});

it('accepts a single needle as a string', function () {
    $rule = ExceptionRule::fromArray([
        'class' => TypeError::class,
        'contains' => 'must be of type',
    ]);

    expect($rule->matches(new TypeError('Argument #1 must be of type string, array given')))->toBeTrue();
});

it('never matches when the configured class does not exist', function () {
    $rule = ExceptionRule::fromArray(['class' => 'Vendor\Package\ClassThatIsNotInstalled']);

    expect($rule->matches(new RuntimeException('boom')))->toBeFalse();
});

it('never matches when the rule has no class', function () {
    $rule = ExceptionRule::fromArray(['contains' => ['boom']]);

    expect($rule->matches(new RuntimeException('boom')))->toBeFalse();
});
