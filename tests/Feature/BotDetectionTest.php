<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Marshmallow\BotShield\Agents\AgentRules;
use Marshmallow\BotShield\Contracts\BotDetector;
use Marshmallow\BotShield\Detectors\BotDetectorFactory;
use Marshmallow\BotShield\Detectors\CrawlerDetectDetector;
use Marshmallow\BotShield\Detectors\RuleAwareBotDetector;
use Marshmallow\BotShield\Detectors\UserAgentBotDetector;
use Marshmallow\BotShield\Enums\AgentAction;
use Marshmallow\BotShield\Facades\BotShield;
use Marshmallow\BotShield\Tests\Fixtures\AlwaysBotDetector;

function agentRequest(string $userAgent): Request
{
    return Request::create('/livewire/update', 'POST', [], [], [], ['HTTP_USER_AGENT' => $userAgent]);
}

describe('agent rules', function () {
    it('returns the action of the first matching rule', function () {
        config()->set('bot-shield.agents.rules', [
            ['pattern' => 'Mozilla', 'action' => 'allow'],
            ['pattern' => 'Googlebot', 'action' => 'block'],
        ]);

        expect(app(AgentRules::class)->actionForUserAgent('Mozilla/5.0 (compatible; Googlebot/2.1)'))
            ->toBe(AgentAction::Allow);
    });

    it('respects rule order when the specific rule comes first', function () {
        config()->set('bot-shield.agents.rules', [
            ['pattern' => 'Googlebot', 'action' => 'block'],
            ['pattern' => 'Mozilla', 'action' => 'allow'],
        ]);

        expect(app(AgentRules::class)->actionForUserAgent('Mozilla/5.0 (compatible; Googlebot/2.1)'))
            ->toBe(AgentAction::Block);
    });

    it('returns no action when nothing matches', function () {
        expect(app(AgentRules::class)->actionForUserAgent('Some Entirely Unknown Client/1.0'))->toBeNull();
    });

    it('returns no action for a blank user agent', function () {
        expect(app(AgentRules::class)->actionFor(Request::create('/livewire/update')))->toBeNull();
    });

    it('skips malformed rules without discarding the valid ones', function () {
        config()->set('bot-shield.agents.rules', [
            ['pattern' => 'curl/'],
            'not even an array',
            ['pattern' => 'Scrapy', 'action' => 'block'],
        ]);

        expect(app(AgentRules::class)->all())->toHaveCount(1)
            ->and(app(AgentRules::class)->actionForUserAgent('Scrapy/2.11'))->toBe(AgentAction::Block);
    });

    it('can list the rules for a single action', function () {
        config()->set('bot-shield.agents.rules', [
            ['pattern' => 'Oh Dear', 'action' => 'allow'],
            ['pattern' => 'curl/', 'action' => 'block'],
            ['pattern' => 'Scrapy', 'action' => 'block'],
        ]);

        expect(app(AgentRules::class)->matching(AgentAction::Block))->toHaveCount(2);
    });
});

describe('rules override the detector', function () {
    it('treats a blocked agent as a bot even though it spoofs a browser', function () {
        expect(BotShield::isBot(agentRequest('Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)')))
            ->toBeTrue();
    });

    it('treats an allowed agent as a real visitor even though it looks like a script', function () {
        expect(BotShield::isBot(agentRequest('Oh Dear Uptime Monitor')))->toBeFalse();
    });

    it('falls back to the detector when no rule matches', function () {
        expect(BotShield::isBot(agentRequest('Some Entirely Unknown Client/1.0')))->toBeTrue()
            ->and(BotShield::isBot(agentRequest('Mozilla/5.0 (Macintosh) Safari/537.36')))->toBeFalse();
    });

    it('treats ignore and challenge agents as bots', function (string $action) {
        config()->set('bot-shield.agents.rules', [
            ['pattern' => 'Mozilla', 'action' => $action],
        ]);

        expect(BotShield::isBot(agentRequest('Mozilla/5.0 (Macintosh) Safari/537.36')))->toBeTrue();
    })->with(['ignore', 'challenge']);
});

describe('detector driver selection', function () {
    it('uses the user agent driver by default', function () {
        $detector = app(BotDetector::class);

        expect($detector)->toBeInstanceOf(RuleAwareBotDetector::class)
            ->and($detector->driver())->toBeInstanceOf(UserAgentBotDetector::class);
    });

    it('builds the crawler detect driver on request', function () {
        config()->set('bot-shield.detector', 'crawler-detect');

        expect(app(BotDetectorFactory::class)->make()->driver())
            ->toBeInstanceOf(CrawlerDetectDetector::class);
    });

    it('detects crawlers that the user agent heuristic would miss', function () {
        config()->set('bot-shield.detector', 'crawler-detect');
        config()->set('bot-shield.agents.rules', []);

        $detector = app(BotDetectorFactory::class)->make();

        expect($detector->isBot(agentRequest('Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)')))
            ->toBeTrue()
            ->and($detector->isBot(agentRequest('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/140.0.0.0 Safari/537.36')))
            ->toBeFalse();
    });

    it('accepts a custom detector class name', function () {
        config()->set('bot-shield.detector', AlwaysBotDetector::class);

        expect(app(BotDetectorFactory::class)->make()->driver())
            ->toBeInstanceOf(AlwaysBotDetector::class);
    });

    it('rejects an unknown driver with a helpful message', function () {
        config()->set('bot-shield.detector', 'telepathy');

        expect(fn () => app(BotDetectorFactory::class)->make())
            ->toThrow(RuntimeException::class, 'Unknown bot detector [telepathy]');
    });

    it('rejects a class that is not a bot detector', function () {
        config()->set('bot-shield.detector', stdClass::class);

        expect(fn () => app(BotDetectorFactory::class)->make())
            ->toThrow(RuntimeException::class, 'must implement');
    });
});

describe('the default agent rules', function () {
    it('block a truncated browser user agent', function (string $userAgent) {
        expect(app(AgentRules::class)->actionForUserAgent($userAgent))->toBe(AgentAction::Block);
    })->with([
        'platform only' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        'platform and engine' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
    ]);

    it('leave a complete browser user agent alone', function (string $userAgent) {
        expect(app(AgentRules::class)->actionForUserAgent($userAgent))->toBeNull();
    })->with([
        'chrome' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',
        'safari' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Version/18.0 Mobile/15E148 Safari/604.1',
        'firefox' => 'Mozilla/5.0 (X11; Linux x86_64; rv:131.0) Gecko/20100101 Firefox/131.0',
    ]);

    it('let an explicit rule above the truncated agent rule win', function () {
        expect(app(AgentRules::class)->actionForUserAgent('Mozilla/5.0 (compatible; GPTBot/1.0)'))
            ->toBe(AgentAction::Deny);
    });
});
