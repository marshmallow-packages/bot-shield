<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Guards;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Marshmallow\BotShield\Agents\AgentRules;
use Marshmallow\BotShield\Contracts\BotDetector;
use Marshmallow\BotShield\Enums\AgentAction;
use Marshmallow\BotShield\Enums\EventType;
use Marshmallow\BotShield\Monitoring\EventRecorder;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Decides whether a guarded form submission may proceed. Page views never reach
 * this, so an agent blocked here can still crawl the site.
 */
final class FormGuard
{
    public function __construct(
        private readonly Repository $config,
        private readonly AgentRules $rules,
        private readonly BotDetector $detector,
        private readonly EventRecorder $recorder,
    ) {}

    public function shouldBlock(Request $request): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $action = $this->rules->actionFor($request);

        if ($action instanceof AgentAction) {
            return $action === AgentAction::Block;
        }

        if (! $this->truthy('bot-shield.forms.use_detector')) {
            return false;
        }

        return $this->detector->isBot($request);
    }

    public function shouldChallenge(Request $request): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        return $this->rules->actionFor($request) === AgentAction::Challenge;
    }

    public function guard(Request $request, ?string $form = null): void
    {
        if (! $this->shouldBlock($request)) {
            return;
        }

        $this->recorder->record(EventType::DetectorBlock, 'blocked', ['form' => $form]);

        throw new HttpException(
            (int) $this->config->get('bot-shield.forms.status', 403),
            (string) trans('bot-shield::messages.forbidden'),
        );
    }

    private function enabled(): bool
    {
        if (! $this->truthy('bot-shield.enabled')) {
            return false;
        }

        return $this->truthy('bot-shield.forms.enabled');
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
