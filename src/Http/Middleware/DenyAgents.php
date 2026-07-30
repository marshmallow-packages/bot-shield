<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Http\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Marshmallow\BotShield\Agents\AgentRules;
use Marshmallow\BotShield\Contracts\RecordsEvents;
use Marshmallow\BotShield\Enums\AgentAction;
use Marshmallow\BotShield\Enums\EventType;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

/**
 * Refuses page views from agents matched by a "deny" rule, which is the runtime
 * half of what bot-shield:robots asks for politely.
 *
 * Not registered by default: robots.txt is the courteous mechanism and most
 * crawlers honour it. Register this only for the ones that do not.
 */
class DenyAgents
{
    public function __construct(
        private readonly Repository $config,
        private readonly AgentRules $rules,
        private readonly RecordsEvents $recorder,
    ) {}

    public function handle(Request $request, Closure $next): BaseResponse
    {
        if (! $this->enabled()) {
            return $next($request);
        }

        $action = $this->rules->actionFor($request);

        if (! $action instanceof AgentAction) {
            return $next($request);
        }

        if (! $action->refusesPageViews()) {
            return $next($request);
        }

        $this->recorder->record(EventType::DetectorBlock, 'denied');

        return new Response('', $this->status());
    }

    private function status(): int
    {
        return (int) $this->config->get('bot-shield.crawlers.status', 403);
    }

    private function enabled(): bool
    {
        if (! $this->truthy('bot-shield.enabled')) {
            return false;
        }

        return $this->truthy('bot-shield.crawlers.deny_page_views');
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
