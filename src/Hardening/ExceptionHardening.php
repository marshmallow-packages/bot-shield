<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Hardening;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Marshmallow\BotShield\Contracts\BotDetector;
use Marshmallow\BotShield\Enums\EventType;
use Marshmallow\BotShield\Monitoring\EventRecorder;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Registers the report suppression and client error rendering callbacks on
 * either the Laravel 11+ exception configuration or a bound legacy
 * App\Exceptions\Handler.
 */
final class ExceptionHardening
{
    public function __construct(
        private readonly Repository $config,
        private readonly Container $container,
        private readonly ExceptionMatcher $matcher,
        private readonly BotDetector $detector,
        private readonly EventRecorder $recorder,
    ) {}

    public function register(Exceptions|Handler $exceptions): void
    {
        if (! $this->enabled()) {
            return;
        }

        $exceptions->dontReport($this->neverReported());

        $exceptions->dontReportWhen(fn (Throwable $exception): bool => $this->shouldNotReport($exception));

        $exceptions->renderable(
            fn (Throwable $exception, Request $request): ?JsonResponse => $this->render($exception, $request),
        );
    }

    public function shouldNotReport(Throwable $exception): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        if ($this->isTransientError($exception)) {
            return true;
        }

        if ($this->isIgnorableClientError($exception)) {
            return true;
        }

        if (! $this->isBotInducedNoise($exception, $this->currentRequest())) {
            return false;
        }

        $this->recorder->record(EventType::SuppressedException, $exception::class);

        return true;
    }

    public function isBotInducedNoise(Throwable $exception, ?Request $request): bool
    {
        if (! $request instanceof Request) {
            return false;
        }

        if (! $this->onProtectedPath($request)) {
            return false;
        }

        if (! $this->matcher->matchesBotNoise($exception)) {
            return false;
        }

        return $this->detector->isBot($request);
    }

    /**
     * Malformed component state is a client mistake, so it renders as a client
     * error for bots and real browsers alike rather than as a 500.
     */
    public function render(Throwable $exception, Request $request): ?JsonResponse
    {
        if (! $this->enabled()) {
            return null;
        }

        if (! $this->onProtectedPath($request)) {
            return null;
        }

        if (! $this->matcher->matchesBotNoise($exception)) {
            return null;
        }

        return new JsonResponse(
            ['message' => trans('bot-shield::messages.invalid_component_data')],
            (int) $this->config->get('bot-shield.exceptions.status', 422),
        );
    }

    private function isTransientError(Throwable $exception): bool
    {
        if (! $this->truthy('bot-shield.exceptions.transient_errors.enabled')) {
            return false;
        }

        return $this->matcher->matchesTransientError($exception);
    }

    private function isIgnorableClientError(Throwable $exception): bool
    {
        if (! $this->truthy('bot-shield.exceptions.client_errors.enabled')) {
            return false;
        }

        if (! $exception instanceof HttpExceptionInterface) {
            return false;
        }

        $status = $exception->getStatusCode();

        if ($status < 400) {
            return false;
        }

        if ($status > 499) {
            return false;
        }

        foreach ((array) $this->config->get('bot-shield.exceptions.client_errors.classes', []) as $class) {
            if (! is_string($class)) {
                continue;
            }

            if ($exception instanceof $class) {
                return true;
            }
        }

        return false;
    }

    private function onProtectedPath(Request $request): bool
    {
        $paths = array_values(array_filter(
            (array) $this->config->get('bot-shield.exceptions.paths', []),
            is_string(...),
        ));

        if ($paths === []) {
            return false;
        }

        return $request->is(...$paths);
    }

    /**
     * @return list<string>
     */
    private function neverReported(): array
    {
        return array_values(array_filter(
            (array) $this->config->get('bot-shield.exceptions.dont_report', []),
            is_string(...),
        ));
    }

    private function currentRequest(): ?Request
    {
        if (! $this->container->bound('request')) {
            return null;
        }

        return $this->container->make('request');
    }

    private function enabled(): bool
    {
        if (! $this->truthy('bot-shield.enabled')) {
            return false;
        }

        return $this->truthy('bot-shield.exceptions.enabled');
    }

    /**
     * Config values are cast leniently on purpose: a hand edited published
     * config must never throw from inside the exception handler.
     */
    private function truthy(string $configKey): bool
    {
        return filter_var(
            $this->config->get($configKey, false),
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE,
        ) === true;
    }
}
