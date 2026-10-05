<?php

declare(strict_types=1);

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\ViewException;
use Livewire\Exceptions\ComponentNotFoundException;
use Livewire\Exceptions\MethodNotFoundException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportReactiveProps\CannotMutateReactivePropException;
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

        expect($handler->shouldReport(new TypeError('Argument #1 ($email) must be of type string, array given')))->toBeTrue();
    });

    it('suppresses always-rules even when the client looks like a browser', function (Throwable $exception) {
        $handler = hardenedHandler(incomingRequest(userAgent: BROWSER_AGENT));

        expect($handler->shouldReport($exception))->toBeFalse();
    })->with([
        'corrupt payload' => fn () => new CorruptComponentPayloadException,
        'locked property' => fn () => new CannotUpdateLockedPropertyException('email'),
    ]);

    it('suppresses matched exceptions a view rethrows for bots', function () {
        $handler = hardenedHandler();
        $inner = new TypeError('App\Livewire\Modal::open(): Argument #1 ($id) must be of type int, array given');

        expect($handler->shouldReport(new ViewException($inner->getMessage().' (View: modal.blade.php)', 0, 1, __FILE__, __LINE__, $inner)))->toBeFalse();
    });

    it('still reports matched exceptions outside the protected paths', function () {
        $handler = hardenedHandler(incomingRequest('/contact', BROWSER_AGENT));

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
     * callback. Livewire 3.8 added render() to these two, returning 419, so from
     * that version on they keep their own status and ours never applies. On
     * earlier Livewire 3 there is no render() and they get our 422.
     *
     * The contract this pins is the one that matters either way: a client error,
     * never a 500. Taking the status over on newer Livewire would mean map(),
     * which also rewrites the exception during reporting and would cost us the
     * real class and stack trace in Sentry.
     */
    it('always answers a client error for self rendering livewire exceptions', function (Throwable $exception) {
        $handler = hardenedHandler();

        $status = $handler->render(incomingRequest(), $exception)->getStatusCode();

        expect($status)->toBeGreaterThanOrEqual(400)
            ->and($status)->toBeLessThan(500)
            ->and($status)->toBeIn([419, 422]);
    })->with([
        'corrupt payload' => fn () => new CorruptComponentPayloadException,
        'locked property' => fn () => new CannotUpdateLockedPropertyException('email'),
    ]);
});

/**
 * @param  array<string, mixed>  $updates
 * @param  array<string, mixed>  $data
 */
function livewireUpdateRequest(string $component, array $updates, array $data = [], string $userAgent = BROWSER_AGENT): Request
{
    $snapshot = json_encode(['data' => $data, 'memo' => ['id' => 'abc', 'name' => $component], 'checksum' => 'x']);

    return Request::create('/livewire/update', 'POST', [
        'components' => [['snapshot' => $snapshot, 'updates' => $updates, 'calls' => []]],
    ], [], [], ['HTTP_USER_AGENT' => $userAgent]);
}

describe('forged livewire requests', function () {
    it('suppresses and renders method names no template can produce, even for browsers', function (string $method) {
        $handler = hardenedHandler(incomingRequest(userAgent: BROWSER_AGENT));
        $exception = new MethodNotFoundException($method);

        expect($handler->shouldReport($exception))->toBeFalse()
            ->and($handler->render(incomingRequest(userAgent: BROWSER_AGENT), $exception)->getStatusCode())->toBe(422);
    })->with([
        'sql probe' => '(select 198766*667891)',
        'variable probe' => '@@PStu1',
        'empty' => '',
        'quote' => "save'",
    ]);

    it('keeps reporting a method name that is a plain typo', function (string $method) {
        $handler = hardenedHandler(incomingRequest(userAgent: BROWSER_AGENT));
        $exception = new MethodNotFoundException($method);

        expect($handler->shouldReport($exception))->toBeTrue()
            ->and($handler->render(incomingRequest(userAgent: BROWSER_AGENT), $exception)->getStatusCode())->toBe(500);
    })->with([
        'typo' => 'submitForm',
        'magic action' => '$refresh',
        'parent call' => '$parent.close',
    ]);

    it('suppresses a reactive prop mutation the request itself forged', function (string $key) {
        $request = livewireUpdateRequest('product-card', [$key => ['forged']]);
        $handler = hardenedHandler($request);
        $exception = new CannotMutateReactivePropException('product-card', 'product');

        expect($handler->shouldReport($exception))->toBeFalse()
            ->and($handler->render($request, $exception)->getStatusCode())->toBe(422);
    })->with([
        'exact key' => 'product',
        'nested key' => 'product.title',
    ]);

    it('keeps reporting a reactive prop the component mutated itself', function (array $updates, string $component) {
        $request = livewireUpdateRequest($component, $updates);
        $handler = hardenedHandler($request);
        $exception = new CannotMutateReactivePropException('product-card', 'product');

        expect($handler->shouldReport($exception))->toBeTrue()
            ->and($handler->render($request, $exception)->getStatusCode())->toBe(500);
    })->with([
        'no updates' => [[], 'product-card'],
        'other prop' => [['quantity' => 2], 'product-card'],
        'prefix only' => [['productId' => 2], 'product-card'],
        'other component' => [['product' => ['forged']], 'cart'],
    ]);

    it('keeps forged calls out of the log through the real report flow', function () {
        $handler = hardenedHandler(incomingRequest(userAgent: BROWSER_AGENT));
        Log::spy();

        $handler->report(new MethodNotFoundException('(select 1)'));
        Log::shouldNotHaveReceived('error');

        $handler->report(new MethodNotFoundException('submitFrom'));
        Log::shouldHaveReceived('error')->once();
    });

    it('keeps reporting a reactive prop mutation when the payload is unreadable', function () {
        $request = Request::create('/livewire/update', 'POST', ['components' => 'nonsense'], [], [], ['HTTP_USER_AGENT' => BROWSER_AGENT]);
        $handler = hardenedHandler($request);

        expect($handler->shouldReport(new CannotMutateReactivePropException('product-card', 'product')))->toBeTrue();
    });
});

describe('forged update types', function () {
    it('suppresses type errors from an array forged into a scalar prop, even for browsers', function (string|int|float|bool $current, Throwable $exception) {
        $request = livewireUpdateRequest('contact', ['email' => ['forged']], ['email' => $current]);
        $handler = hardenedHandler($request);

        expect($handler->shouldReport($exception))->toBeFalse();
    })->with([
        'string' => 'jane@example.com',
        'int' => 3,
        'float' => 1.5,
        'bool' => false,
    ])->with([
        'typed property' => fn () => new TypeError('Cannot assign array to property App\\Livewire\\Contact::$email of type string'),
        'wrapped by a view' => fn () => new ViewException('htmlspecialchars(): Argument #1 ($string) must be of type string, array given', 0, 1, __FILE__, __LINE__, new TypeError('htmlspecialchars(): Argument #1 ($string) must be of type string, array given')),
        'array offset' => fn () => new ErrorException('Trying to access array offset on value of type int'),
    ]);

    it('keeps reporting browsers whose updates match the snapshot types', function (array $updates, array $data) {
        $handler = hardenedHandler(livewireUpdateRequest('contact', $updates, $data));

        expect($handler->shouldReport(new TypeError('Cannot assign array to property App\\Livewire\\Contact::$email of type string')))->toBeTrue();
    })->with([
        'scalar into scalar' => [['email' => 'jane@example.com'], ['email' => '']],
        'string into int' => [['quantity' => '5'], ['quantity' => 1]],
        'array into null' => [['tags' => ['a']], ['tags' => null]],
        'array into array' => [['tags' => ['a']], ['tags' => [[], ['s' => 'arr']]]],
        'unknown prop' => [['tags' => ['a']], []],
        'nested key' => [['address.street' => ['a']], ['address' => 'x']],
    ]);

    it('keeps reporting unmatched exceptions on a forged update', function () {
        $handler = hardenedHandler(livewireUpdateRequest('contact', ['email' => ['forged']], ['email' => '']));

        expect($handler->shouldReport(new RuntimeException('the database is actually on fire')))->toBeTrue();
    });

    it('asks the detector again once forged update detection is off', function () {
        config()->set('bot-shield.exceptions.forged_updates', false);

        $handler = hardenedHandler(livewireUpdateRequest('contact', ['email' => ['forged']], ['email' => '']));

        expect($handler->shouldReport(new TypeError('Cannot assign array to property App\\Livewire\\Contact::$email of type string')))->toBeTrue();
    });

    it('ignores a snapshot it cannot read', function () {
        $request = Request::create('/livewire/update', 'POST', [
            'components' => [['snapshot' => 'nonsense', 'updates' => ['email' => ['forged']]], 'nonsense'],
        ], [], [], ['HTTP_USER_AGENT' => BROWSER_AGENT]);

        expect(hardenedHandler($request)->shouldReport(new TypeError('Argument #1 must be of type string, array given')))->toBeTrue();
    });
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
