<?php

declare(strict_types=1);

namespace Marshmallow\BotShield;

use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\Request;
use Marshmallow\BotShield\Contracts\BotDetector;
use Marshmallow\BotShield\Hardening\ExceptionHardening;

class BotShield
{
    public function __construct(
        private readonly ExceptionHardening $hardening,
        private readonly BotDetector $detector,
    ) {}

    /**
     * Register the exception hardening callbacks. Mirrors the ergonomics of
     * Sentry's integration so it reads naturally inside withExceptions():
     *
     *     ->withExceptions(function (Exceptions $exceptions) {
     *         BotShield::handles($exceptions);
     *     })
     */
    public function handles(Exceptions|Handler $exceptions): void
    {
        $this->hardening->register($exceptions);
    }

    public function isBot(?Request $request = null): bool
    {
        return $this->detector->isBot($request ?? request());
    }

    public function detector(): BotDetector
    {
        return $this->detector;
    }
}
