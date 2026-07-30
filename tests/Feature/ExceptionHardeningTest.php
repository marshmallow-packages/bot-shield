<?php

declare(strict_types=1);

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\Request;
use Livewire\Exceptions\ComponentNotFoundException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Mechanisms\HandleComponents\CorruptComponentPayloadException;
use Marshmallow\BotShield\Facades\BotShield;
use Marshmallow\BotShield\Tests\Fixtures\ApiClientException;
use Marshmallow\BotShield\Tests\Fixtures\LegacyHandlerStub;
use Marshmallow\BotShield\Tests\Fixtures\NotAHandlerStub;
use Symfony\Component\HttpKernel\Exception\HttpException;

const BROWSER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/140.0.0.0 Safari/537.36';

const SCRIPT_AGENT = 'curl/8.7.1';

function incomingRequest(string $path = '/livewire/update', string $userAgent = SCRIPT_AGENT): Request
{
    return Request::create($path, 'POST', [], [], [], ['HTTP_USER_AGENT' => $userAgent]);
}

function hardenedHandler(?Request $request = null): Handler
{
    $request ??= incomingRequest();

    app()->instance('request', $request);

    $handler = app(ExceptionHandler::class);

    expect($handler)->toBeInstanceOf(Handler::class);

    BotShield::handles(new Exceptions($handler));

    return $handler;
}

function transientQueryException(string $message): QueryException
{
    return new QueryException('mysql', 'select 1', [], new RuntimeException($message));
}

describe('report suppression', function () {
    it('suppresses matched livewire exceptions for bots', function (Throwable $exception) {
        $handler = hardenedHandler();

        expect($handler->shouldReport($exception))->toBeFalse();
    })->with([
        'corrupt payload' => fn () => new CorruptComponentPayloadException,
        'locked property' => fn () => new CannotUpdateLockedPropertyException('email'),
        'missing component' => fn () => new ComponentNotFoundException('Unable to find component: [filament.pages.auth.login]'),
        'typed property hydration' => fn () => new TypeError('Cannot assign array to property App\Livewire\Contact::$email of type string'),
        'argument type' => fn () => new TypeError('App\Livewire\Contact::setEmail(): Argument #1 ($email) must be of type string, array given'),
        'array offset on null' => fn () => new ErrorException('Trying to access array offset on value of type null'),
    ]);

    it('still reports matched exceptions from real browsers', function () {
        $handler = hardenedHandler(incomingRequest(userAgent: BROWSER_AGENT));

        expect($handler->shouldReport(new CorruptComponentPayloadException))->toBeTrue();
    });

    it('still reports matched exceptions outside the protected paths', function () {
        $handler = hardenedHandler(incomingRequest('/contact'));

        expect($handler->shouldReport(new CorruptComponentPayloadException))->toBeTrue();
    });

    it('still reports unmatched exceptions from bots on protected paths', function () {
        $handler = hardenedHandler();

        expect($handler->shouldReport(new RuntimeException('the database is actually on fire')))->toBeTrue();
    });

    it('registers nothing when the package is disabled', function () {
        config()->set('bot-shield.enabled', false);

        $handler = hardenedHandler();

        expect($handler->shouldReport(new CorruptComponentPayloadException))->toBeTrue();
    });

    it('registers nothing when exception hardening is disabled', function () {
        config()->set('bot-shield.exceptions.enabled', false);

        $handler = hardenedHandler();

        expect($handler->shouldReport(new CorruptComponentPayloadException))->toBeTrue();
    });

    it('honours protected paths added through config', function () {
        config()->set('bot-shield.exceptions.paths', ['custom-endpoint/*']);

        $handler = hardenedHandler(incomingRequest('/custom-endpoint/update'));

        expect($handler->shouldReport(new CorruptComponentPayloadException))->toBeFalse();
    });

    it('honours matcher rules added through config', function () {
        config()->set('bot-shield.exceptions.rules', [
            ['class' => RuntimeException::class, 'contains' => ['probing']],
        ]);

        $handler = hardenedHandler();

        expect($handler->shouldReport(new RuntimeException('someone is probing us')))->toBeFalse()
            ->and($handler->shouldReport(new CorruptComponentPayloadException))->toBeTrue();
    });

    it('never reports the classes listed in dont_report', function () {
        config()->set('bot-shield.exceptions.dont_report', [LogicException::class]);

        $handler = hardenedHandler(incomingRequest('/contact', BROWSER_AGENT));

        expect($handler->shouldReport(new LogicException('from a real browser on a normal page')))->toBeFalse();
    });
});

describe('client error rendering', function () {
    it('renders matched livewire exceptions as a client error', function () {
        $handler = hardenedHandler();

        $response = $handler->render(incomingRequest(), new ComponentNotFoundException('Unable to find component'));

        expect($response->getStatusCode())->toBe(422)
            ->and(json_decode((string) $response->getContent(), true))
            ->toBe(['message' => 'Invalid component data.']);
    });

    it('renders a client error for real browsers too, because malformed state is never a server fault', function () {
        $handler = hardenedHandler();
        $request = incomingRequest(userAgent: BROWSER_AGENT);

        $response = $handler->render($request, new ComponentNotFoundException('Unable to find component'));

        expect($response->getStatusCode())->toBe(422);
    });

    it('honours a configured status code', function () {
        config()->set('bot-shield.exceptions.status', 400);

        $handler = hardenedHandler();

        $response = $handler->render(incomingRequest(), new ComponentNotFoundException('Unable to find component'));

        expect($response->getStatusCode())->toBe(400);
    });

    it('leaves unmatched exceptions to the framework', function () {
        $handler = hardenedHandler();

        $response = $handler->render(incomingRequest(), new HttpException(503, 'Down for maintenance'));

        expect($response->getStatusCode())->toBe(503);
    });

    it('leaves matched exceptions outside the protected paths to the framework', function () {
        $handler = hardenedHandler();

        $response = $handler->render(incomingRequest('/contact'), new ComponentNotFoundException('Unable to find component'));

        expect($response->getStatusCode())->toBe(500);
    });

    /*
     * Laravel consults an exception's own render() method before any renderable
     * callback, so the two Livewire exceptions that define one keep answering
     * 419. That still satisfies the goal, a client error rather than a 500, and
     * taking it over would mean map(), which also rewrites the exception during
     * reporting and would cost us the real class and stack trace in Sentry.
     */
    it('leaves the status of self rendering livewire exceptions to livewire', function (Throwable $exception) {
        $handler = hardenedHandler();

        $response = $handler->render(incomingRequest(), $exception);

        expect($response->getStatusCode())->toBe(419);
    })->with([
        'corrupt payload' => fn () => new CorruptComponentPayloadException,
        'locked property' => fn () => new CannotUpdateLockedPropertyException('email'),
    ]);
});

describe('optional extras', function () {
    it('reports transient database errors by default', function () {
        $handler = hardenedHandler();

        expect($handler->shouldReport(transientQueryException('MySQL server has gone away')))->toBeTrue();
    });

    it('suppresses transient database errors once enabled', function () {
        config()->set('bot-shield.exceptions.transient_errors.enabled', true);

        $handler = hardenedHandler();

        expect($handler->shouldReport(transientQueryException('MySQL server has gone away')))->toBeFalse()
            ->and($handler->shouldReport(transientQueryException('Deadlock found when trying to get lock')))->toBeFalse();
    });

    it('keeps reporting database errors that are not transient', function () {
        config()->set('bot-shield.exceptions.transient_errors.enabled', true);

        $handler = hardenedHandler();

        expect($handler->shouldReport(transientQueryException('Unknown column "wat" in field list')))->toBeTrue();
    });

    it('suppresses 4xx exceptions only for the configured client error classes', function () {
        config()->set('bot-shield.exceptions.client_errors.enabled', true);
        config()->set('bot-shield.exceptions.client_errors.classes', [ApiClientException::class]);

        $handler = hardenedHandler();

        expect($handler->shouldReport(new ApiClientException(422, 'Unprocessable')))->toBeFalse()
            ->and($handler->shouldReport(new ApiClientException(503, 'Service unavailable')))->toBeTrue();
    });

    it('suppresses no 4xx exceptions while the extra is disabled', function () {
        config()->set('bot-shield.exceptions.client_errors.classes', [ApiClientException::class]);

        $handler = hardenedHandler();

        expect($handler->shouldReport(new ApiClientException(422, 'Unprocessable')))->toBeTrue();
    });

    it('suppresses no 4xx exceptions while no client error classes are configured', function () {
        config()->set('bot-shield.exceptions.client_errors.enabled', true);

        $handler = hardenedHandler();

        expect($handler->shouldReport(new ApiClientException(422, 'Unprocessable')))->toBeTrue();
    });
});

describe('legacy handler wiring', function () {
    it('hardens a bound legacy handler through the trait', function () {
        app()->instance('request', incomingRequest());

        $handler = new LegacyHandlerStub(app());
        $handler->hardenAgainstBots();

        expect($handler->shouldReport(new CorruptComponentPayloadException))->toBeFalse();
    });

    it('refuses to be used outside an exception handler', function () {
        expect(fn () => (new NotAHandlerStub)->hardenAgainstBots())
            ->toThrow(RuntimeException::class, 'may only be used on a class extending');
    });
});
