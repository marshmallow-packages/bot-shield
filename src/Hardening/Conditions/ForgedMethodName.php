<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Hardening\Conditions;

use Illuminate\Http\Request;
use Marshmallow\BotShield\Contracts\ExceptionCondition;
use Throwable;

/**
 * A called method that is not an identifier, such as "(select 1)", cannot come
 * from a template. A plain typo like "submitFrom" still reports.
 */
final class ForgedMethodName implements ExceptionCondition
{
    public function matches(Throwable $exception, Request $request): bool
    {
        if (preg_match('/Public method \[(.*)\] not found on component/s', $exception->getMessage(), $matches) !== 1) {
            return false;
        }

        return preg_match('/^[A-Za-z_$][A-Za-z0-9_$.]*$/', $matches[1]) !== 1;
    }
}
