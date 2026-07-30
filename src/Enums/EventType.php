<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Enums;

enum EventType: string
{
    case Captcha = 'captcha';

    case Honeypot = 'honeypot';

    case DetectorBlock = 'detector-block';

    case SuppressedException = 'suppressed-exception';

    case ProbePath = 'probe-path';

    public function label(): string
    {
        return match ($this) {
            self::Captcha => 'Captcha verifications',
            self::Honeypot => 'Honeypot trips',
            self::DetectorBlock => 'Blocked submissions',
            self::SuppressedException => 'Suppressed exceptions',
            self::ProbePath => 'Probe path hits',
        };
    }
}
