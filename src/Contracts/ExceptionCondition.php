<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Contracts;

use Illuminate\Http\Request;
use Throwable;

/**
 * Narrows an exception rule by looking at the request that caused it, for
 * shapes the exception class and message alone cannot tell apart.
 */
interface ExceptionCondition
{
    public function matches(Throwable $exception, Request $request): bool;
}
