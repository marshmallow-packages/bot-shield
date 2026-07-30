<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Detectors;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Jaybizzle\CrawlerDetect\CrawlerDetect;
use Marshmallow\BotShield\Agents\AgentRules;
use Marshmallow\BotShield\Contracts\BotDetector;
use RuntimeException;

final class BotDetectorFactory
{
    public const string USER_AGENT = 'user-agent';

    public const string CRAWLER_DETECT = 'crawler-detect';

    public function __construct(
        private readonly Repository $config,
        private readonly Container $container,
        private readonly AgentRules $rules,
    ) {}

    public function make(?string $driver = null): BotDetector
    {
        $driver ??= (string) $this->config->get('bot-shield.detector', self::USER_AGENT);

        return new RuleAwareBotDetector($this->rules, $this->makeDriver($driver));
    }

    private function makeDriver(string $driver): BotDetector
    {
        return match ($driver) {
            self::USER_AGENT => new UserAgentBotDetector,
            self::CRAWLER_DETECT => $this->makeCrawlerDetectDriver(),
            default => $this->makeCustomDriver($driver),
        };
    }

    private function makeCrawlerDetectDriver(): BotDetector
    {
        if (! class_exists(CrawlerDetect::class)) {
            throw new RuntimeException(sprintf(
                'The [%s] bot detector requires jaybizzle/crawler-detect. Run: composer require jaybizzle/crawler-detect',
                self::CRAWLER_DETECT,
            ));
        }

        return new CrawlerDetectDetector(new CrawlerDetect);
    }

    private function makeCustomDriver(string $driver): BotDetector
    {
        if (! class_exists($driver)) {
            throw new RuntimeException(sprintf(
                'Unknown bot detector [%s]. Use [%s], [%s], or the class name of a %s implementation.',
                $driver,
                self::USER_AGENT,
                self::CRAWLER_DETECT,
                BotDetector::class,
            ));
        }

        $detector = $this->container->make($driver);

        if (! $detector instanceof BotDetector) {
            throw new RuntimeException(sprintf(
                'The bot detector [%s] must implement %s.',
                $driver,
                BotDetector::class,
            ));
        }

        return $detector;
    }
}
