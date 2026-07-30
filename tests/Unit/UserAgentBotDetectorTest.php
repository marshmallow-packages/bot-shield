<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Marshmallow\BotShield\Detectors\UserAgentBotDetector;

function requestWithUserAgent(?string $userAgent): Request
{
    $server = $userAgent === null ? [] : ['HTTP_USER_AGENT' => $userAgent];

    return Request::create('/livewire/update', 'POST', [], [], [], $server);
}

it('treats user agents advertising Mozilla as real browsers', function (string $userAgent) {
    expect((new UserAgentBotDetector)->isBot(requestWithUserAgent($userAgent)))->toBeFalse();
})->with([
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',
    'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Version/18.0 Mobile/15E148 Safari/604.1',
    'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
]);

it('treats scripted clients as bots', function (string $userAgent) {
    expect((new UserAgentBotDetector)->isBot(requestWithUserAgent($userAgent)))->toBeTrue();
})->with([
    'curl/8.7.1',
    'python-requests/2.32.3',
    'Go-http-client/1.1',
    'Scrapy/2.11.2 (+https://scrapy.org)',
]);

it('treats a missing user agent as a bot', function () {
    expect((new UserAgentBotDetector)->isBot(requestWithUserAgent(null)))->toBeTrue();
});

it('treats an empty user agent as a bot', function () {
    expect((new UserAgentBotDetector)->isBot(requestWithUserAgent('')))->toBeTrue();
});
