<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Livewire\Exceptions\MethodNotFoundException;
use Marshmallow\BotShield\Hardening\Conditions\ForgedMethodName;
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

it('matches an exception wrapped by another one', function () {
    $rule = ExceptionRule::fromArray(['class' => TypeError::class, 'contains' => ['must be of type']]);
    $wrapped = new RuntimeException('rendering failed', 0, new TypeError('Argument #1 must be of type string, array given'));

    expect($rule->matches($wrapped))->toBeTrue();
});

it('does not match when nothing in the chain qualifies', function () {
    $rule = ExceptionRule::fromArray(['class' => TypeError::class]);
    $wrapped = new RuntimeException('outer', 0, new LogicException('inner'));

    expect($rule->matches($wrapped))->toBeFalse();
});

it('only treats a literal true as always', function (mixed $value, bool $expected) {
    expect(ExceptionRule::fromArray(['class' => RuntimeException::class, 'always' => $value])->always)->toBe($expected);
})->with([
    'true' => [true, true],
    'string' => ['true', false],
    'one' => [1, false],
    'absent' => [null, false],
]);

it('requires the condition to accept the request when one is configured', function () {
    $rule = ExceptionRule::fromArray(['class' => MethodNotFoundException::class, 'when' => ForgedMethodName::class]);
    $request = Request::create('/livewire/update', 'POST');

    expect($rule->matches(new MethodNotFoundException('(select 1)'), $request))->toBeTrue()
        ->and($rule->matches(new MethodNotFoundException('save'), $request))->toBeFalse();
});

it('never matches a conditional rule without a request', function () {
    $rule = ExceptionRule::fromArray(['class' => MethodNotFoundException::class, 'when' => ForgedMethodName::class]);

    expect($rule->matches(new MethodNotFoundException('(select 1)')))->toBeFalse();
});

it('never matches when the condition is not an exception condition', function (mixed $when) {
    $rule = ExceptionRule::fromArray(['class' => MethodNotFoundException::class, 'when' => $when]);
    $request = Request::create('/livewire/update', 'POST');

    expect($rule->matches(new MethodNotFoundException('(select 1)'), $request))->toBeFalse();
})->with([
    'unrelated class' => stdClass::class,
    'missing class' => 'Vendor\\Package\\ClassThatIsNotInstalled',
]);

it('ignores a condition that is not a string', function () {
    $rule = ExceptionRule::fromArray(['class' => RuntimeException::class, 'when' => ['nope']]);

    expect($rule->when)->toBeNull()
        ->and($rule->matches(new RuntimeException('anything')))->toBeTrue();
});
