<?php

declare(strict_types=1);

use Marshmallow\BotShield\Agents\AgentRule;
use Marshmallow\BotShield\Enums\AgentAction;

it('matches a substring regardless of case', function () {
    $rule = AgentRule::fromArray(['pattern' => 'GPTBot', 'action' => 'block']);

    expect($rule?->matches('Mozilla/5.0 (compatible; gptbot/1.2)'))->toBeTrue()
        ->and($rule?->matches('Mozilla/5.0 (compatible; GPTBot/1.2)'))->toBeTrue()
        ->and($rule?->matches('Mozilla/5.0 (compatible; Googlebot/2.1)'))->toBeFalse();
});

it('matches a regular expression when told to', function () {
    $rule = AgentRule::fromArray([
        'pattern' => '/^curl\/[0-9]+/',
        'action' => 'block',
        'regex' => true,
    ]);

    expect($rule?->matches('curl/8.7.1'))->toBeTrue()
        ->and($rule?->matches('Mozilla/5.0 curl/8.7.1'))->toBeFalse();
});

it('treats an invalid regular expression as never matching instead of erroring', function () {
    $rule = AgentRule::fromArray([
        'pattern' => '/unterminated(/',
        'action' => 'block',
        'regex' => true,
    ]);

    expect($rule?->matches('anything'))->toBeFalse();
});

it('reads the action into the enum', function (string $action, AgentAction $expected) {
    $rule = AgentRule::fromArray(['pattern' => 'Something', 'action' => $action]);

    expect($rule?->action)->toBe($expected);
})->with([
    ['allow', AgentAction::Allow],
    ['ignore', AgentAction::Ignore],
    ['challenge', AgentAction::Challenge],
    ['block', AgentAction::Block],
    ['BLOCK', AgentAction::Block],
]);

it('discards malformed rules', function (array $rule) {
    expect(AgentRule::fromArray($rule))->toBeNull();
})->with([
    'no pattern' => [['action' => 'block']],
    'empty pattern' => [['pattern' => '', 'action' => 'block']],
    'non string pattern' => [['pattern' => ['nope'], 'action' => 'block']],
    'no action' => [['pattern' => 'curl/']],
    'unknown action' => [['pattern' => 'curl/', 'action' => 'incinerate']],
    'non string action' => [['pattern' => 'curl/', 'action' => 7]],
]);

it('knows which actions treat a request as a bot', function () {
    expect(AgentAction::Allow->treatsRequestAsBot())->toBeFalse()
        ->and(AgentAction::Ignore->treatsRequestAsBot())->toBeTrue()
        ->and(AgentAction::Challenge->treatsRequestAsBot())->toBeTrue()
        ->and(AgentAction::Block->treatsRequestAsBot())->toBeTrue();
});
