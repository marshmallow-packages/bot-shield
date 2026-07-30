<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Enums;

enum CaptchaOutcome: string
{
    /** No captcha is active, so there was nothing to verify. */
    case Skipped = 'skipped';

    case Passed = 'passed';

    /** The submission carried no token, which a scripted post never does. */
    case MissingToken = 'missing-token';

    /** The provider rejected the token outright. */
    case Failed = 'failed';

    /** The token was valid but the score fell below the threshold. */
    case LowScore = 'low-score';

    /** The provider could not be reached. */
    case Unavailable = 'unavailable';

    public function messageKey(): string
    {
        return match ($this) {
            self::Skipped, self::Passed => 'bot-shield::messages.recaptcha.passed',
            self::MissingToken => 'bot-shield::messages.recaptcha.missing',
            self::Failed, self::LowScore => 'bot-shield::messages.recaptcha.failed',
            self::Unavailable => 'bot-shield::messages.recaptcha.unavailable',
        };
    }
}
