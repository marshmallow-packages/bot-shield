<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Detectors;

use Illuminate\Http\Request;
use Jaybizzle\CrawlerDetect\CrawlerDetect;
use Marshmallow\BotShield\Contracts\BotDetector;

/**
 * The stricter driver: matches the user agent against crawler-detect's
 * maintained crawler list instead of only looking for "Mozilla/".
 */
final class CrawlerDetectDetector implements BotDetector
{
    public function __construct(
        private readonly CrawlerDetect $crawlerDetect,
    ) {}

    public function isBot(Request $request): bool
    {
        $userAgent = $request->userAgent();

        if (blank($userAgent)) {
            return true;
        }

        return $this->crawlerDetect->isCrawler($userAgent);
    }
}
