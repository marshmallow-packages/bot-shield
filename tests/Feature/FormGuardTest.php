<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Marshmallow\BotShield\Guards\FormGuard;
use Symfony\Component\HttpKernel\Exception\HttpException;

function submitRequest(string $userAgent): Request
{
    return Request::create('/contact', 'POST', [], [], [], ['HTTP_USER_AGENT' => $userAgent]);
}

it('blocks agents matched by a block rule', function () {
    expect(app(FormGuard::class)->shouldBlock(submitRequest('curl/8.7.1')))->toBeTrue();
});

it('blocks search engines from submitting while leaving page views alone', function () {
    expect(app(FormGuard::class)->shouldBlock(submitRequest('Mozilla/5.0 (compatible; Googlebot/2.1)')))->toBeTrue();
});

it('allows agents matched by an allow rule', function () {
    expect(app(FormGuard::class)->shouldBlock(submitRequest('Oh Dear Uptime Monitor')))->toBeFalse();
});

it('allows ignore and challenge agents to submit', function (string $action) {
    config()->set('bot-shield.agents.rules', [
        ['pattern' => 'SomeClient', 'action' => $action],
    ]);

    expect(app(FormGuard::class)->shouldBlock(submitRequest('SomeClient/1.0')))->toBeFalse();
})->with(['ignore', 'challenge']);

it('allows unmatched agents through by default, even scripted ones', function () {
    expect(app(FormGuard::class)->shouldBlock(submitRequest('Some Entirely Unknown Client/1.0')))->toBeFalse();
});

it('blocks anything the detector flags once use_detector is enabled', function () {
    config()->set('bot-shield.forms.use_detector', true);

    expect(app(FormGuard::class)->shouldBlock(submitRequest('Some Entirely Unknown Client/1.0')))->toBeTrue()
        ->and(app(FormGuard::class)->shouldBlock(submitRequest('Mozilla/5.0 (Macintosh) Safari/537.36')))->toBeFalse();
});

it('blocks nothing while the form guard is disabled', function () {
    config()->set('bot-shield.forms.enabled', false);

    expect(app(FormGuard::class)->shouldBlock(submitRequest('curl/8.7.1')))->toBeFalse();
});

it('blocks nothing while the package is disabled', function () {
    config()->set('bot-shield.enabled', false);

    expect(app(FormGuard::class)->shouldBlock(submitRequest('curl/8.7.1')))->toBeFalse();
});

it('reports which agents must be challenged', function () {
    config()->set('bot-shield.agents.rules', [
        ['pattern' => 'SuspiciousClient', 'action' => 'challenge'],
        ['pattern' => 'curl/', 'action' => 'block'],
    ]);

    $guard = app(FormGuard::class);

    expect($guard->shouldChallenge(submitRequest('SuspiciousClient/1.0')))->toBeTrue()
        ->and($guard->shouldChallenge(submitRequest('curl/8.7.1')))->toBeFalse();
});

it('throws a forbidden response when guarding a blocked request', function () {
    expect(fn () => app(FormGuard::class)->guard(submitRequest('curl/8.7.1')))
        ->toThrow(HttpException::class, 'This request was blocked.');
});

it('honours a configured block status', function () {
    config()->set('bot-shield.forms.status', 404);

    try {
        app(FormGuard::class)->guard(submitRequest('curl/8.7.1'));
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(404);

        return;
    }

    $this->fail('The guard did not block the request.');
});

it('lets an allowed request pass the guard without throwing', function () {
    app(FormGuard::class)->guard(submitRequest('Mozilla/5.0 (Macintosh) Safari/537.36'));
})->throwsNoExceptions();
