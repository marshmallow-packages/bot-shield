<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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

/**
 * Give the captcha the keys it needs to consider itself configured.
 */
function configureCaptcha(string $driver = 'google-v3'): void
{
    config()->set('bot-shield.captcha.driver', $driver);
    config()->set('bot-shield.captcha.site_key', 'test-site-key');
    config()->set('bot-shield.captcha.secret_key', 'test-secret-key');
}

/**
 * @param  array<string, mixed>  $payload
 */
function fakeSiteverify(array $payload): void
{
    Http::fake([
        'https://www.google.com/recaptcha/api/siteverify' => Http::response($payload),
    ]);
}
