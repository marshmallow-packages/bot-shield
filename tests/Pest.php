<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Marshmallow\BotShield\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/**
 * Bind an incoming request with the given user agent, for the code paths that
 * resolve the current request from the container.
 */
function withUserAgent(string $userAgent, string $path = '/livewire/update'): Request
{
    $request = Request::create($path, 'POST', [], [], [], ['HTTP_USER_AGENT' => $userAgent]);

    app()->instance('request', $request);

    return $request;
}
