<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Http\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Marshmallow\BotShield\Contracts\RecordsEvents;
use Marshmallow\BotShield\Enums\EventType;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

/**
 * Answers known scanner paths immediately.
 *
 * Register this as the first global middleware. It returns a response rather
 * than aborting, so there is no exception to report, and it runs before the
 * session and route stack, so a scan for wp-login.php costs almost nothing.
 */
class BlockProbePaths
{
    public function __construct(
        private readonly Repository $config,
        private readonly RecordsEvents $recorder,
    ) {}

    public function handle(Request $request, Closure $next): BaseResponse
    {
        if (! $this->enabled()) {
            return $next($request);
        }

        if (! $this->isProbe($request)) {
            return $next($request);
        }

        $this->recorder->record(EventType::ProbePath, $request->path());

        return new Response('', $this->status());
    }

    private function isProbe(Request $request): bool
    {
        $paths = array_values(array_filter(
            (array) $this->config->get('bot-shield.probe_paths.paths', []),
            is_string(...),
        ));

        if ($paths === []) {
            return false;
        }

        return $request->is(...$paths);
    }

    private function status(): int
    {
        return (int) $this->config->get('bot-shield.probe_paths.status', 404);
    }

    private function enabled(): bool
    {
        if (! $this->truthy('bot-shield.enabled')) {
            return false;
        }

        return $this->truthy('bot-shield.probe_paths.enabled');
    }

    private function truthy(string $configKey): bool
    {
        return filter_var(
            $this->config->get($configKey, false),
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE,
        ) === true;
    }
}
