<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Marshmallow\BotShield\Enums\EventType;
use Marshmallow\BotShield\Http\Middleware\BlockProbePaths;
use Marshmallow\BotShield\Http\Middleware\DenyAgents;
use Marshmallow\BotShield\Models\BotShieldEvent;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

function passThrough(): Closure
{
    return fn (): BaseResponse => new Response('reached the app');
}

function probeRequest(string $path, string $userAgent = 'curl/8.7.1'): Request
{
    return Request::create('/'.ltrim($path, '/'), 'GET', [], [], [], ['HTTP_USER_AGENT' => $userAgent]);
}

describe('the probe path firewall', function () {
    it('answers known scanner paths without reaching the app', function (string $path) {
        $response = app(BlockProbePaths::class)->handle(probeRequest($path), passThrough());

        expect($response->getStatusCode())->toBe(404)
            ->and($response->getContent())->toBe('');
    })->with([
        'wp-login.php',
        'wp-admin',
        'wp-admin/setup-config.php',
        'wp-content/plugins/thing.php',
        'xmlrpc.php',
        '.env',
        '.git/config',
        'phpmyadmin',
        'vendor/phpunit/phpunit/src/Util/PHP/eval-stdin.php',
    ]);

    it('lets ordinary paths through', function (string $path) {
        $response = app(BlockProbePaths::class)->handle(probeRequest($path), passThrough());

        expect($response->getContent())->toBe('reached the app');
    })->with(['/', 'contact', 'blog/some-post', 'admin/dashboard']);

    it('records the probe so it shows up in the stats', function () {
        app(BlockProbePaths::class)->handle(probeRequest('wp-login.php'), passThrough());

        expect(BotShieldEvent::query()->sole())
            ->type->toBe(EventType::ProbePath)
            ->outcome->toBe('wp-login.php');
    });

    it('does nothing while disabled', function () {
        config()->set('bot-shield.probe_paths.enabled', false);

        $response = app(BlockProbePaths::class)->handle(probeRequest('wp-login.php'), passThrough());

        expect($response->getContent())->toBe('reached the app');
    });

    it('does nothing while the package is disabled', function () {
        config()->set('bot-shield.enabled', false);

        $response = app(BlockProbePaths::class)->handle(probeRequest('wp-login.php'), passThrough());

        expect($response->getContent())->toBe('reached the app');
    });

    it('honours a configured status', function () {
        config()->set('bot-shield.probe_paths.status', 410);

        expect(app(BlockProbePaths::class)->handle(probeRequest('.env'), passThrough())->getStatusCode())
            ->toBe(410);
    });

    it('works as real middleware on a route', function () {
        Route::middleware(['bot-shield.probe-paths'])->get('/wp-login.php', fn () => 'reached the app');

        $this->get('/wp-login.php')->assertNotFound();
    });
});

describe('the crawler denial middleware', function () {
    beforeEach(fn () => config()->set('bot-shield.crawlers.deny_page_views', true));

    it('refuses page views from a denied agent', function () {
        $response = app(DenyAgents::class)->handle(probeRequest('/', 'GPTBot/1.2'), passThrough());

        expect($response->getStatusCode())->toBe(403);
    });

    it('lets blocked agents keep crawling, since they only lose form submissions', function () {
        $response = app(DenyAgents::class)->handle(
            probeRequest('/', 'Mozilla/5.0 (compatible; Googlebot/2.1)'),
            passThrough(),
        );

        expect($response->getContent())->toBe('reached the app');
    });

    it('lets allowed and unmatched agents through', function (string $userAgent) {
        $response = app(DenyAgents::class)->handle(probeRequest('/', $userAgent), passThrough());

        expect($response->getContent())->toBe('reached the app');
    })->with([
        'Oh Dear Uptime Monitor',
        'Mozilla/5.0 (Macintosh) Safari/537.36',
        'Some Entirely Unknown Client/1.0',
    ]);

    it('is inert until switched on, because robots.txt is the polite mechanism', function () {
        config()->set('bot-shield.crawlers.deny_page_views', false);

        $response = app(DenyAgents::class)->handle(probeRequest('/', 'GPTBot/1.2'), passThrough());

        expect($response->getContent())->toBe('reached the app');
    });

    it('records the refusal', function () {
        app(DenyAgents::class)->handle(probeRequest('/', 'GPTBot/1.2'), passThrough());

        expect(BotShieldEvent::query()->sole())
            ->type->toBe(EventType::DetectorBlock)
            ->outcome->toBe('denied');
    });
});
