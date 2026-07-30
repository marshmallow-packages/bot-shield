<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Enums;

/**
 * What to do with a request whose user agent matches an agent rule. Rules are
 * evaluated before any detector, so a matching rule always wins.
 */
enum AgentAction: string
{
    /**
     * Never treated as a bot. Use for uptime monitors and your own tooling,
     * whose exceptions you do want to hear about.
     */
    case Allow = 'allow';

    /**
     * Treated as a bot, so its exceptions are not reported, but it may still
     * browse and submit forms.
     */
    case Ignore = 'ignore';

    /**
     * Treated as a bot and always forced through the captcha, whatever score it
     * would otherwise have earned.
     */
    case Challenge = 'challenge';

    /**
     * Treated as a bot and refused on guarded form submissions. Page views are
     * unaffected, so search engines can keep crawling while never submitting.
     */
    case Block = 'block';

    public static function tryFromValue(mixed $value): ?self
    {
        if (! is_string($value)) {
            return null;
        }

        return self::tryFrom(strtolower($value));
    }

    public function treatsRequestAsBot(): bool
    {
        return $this !== self::Allow;
    }
}
