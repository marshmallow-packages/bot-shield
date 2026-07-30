<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Livewire\Exceptions\ComponentNotFoundException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Mechanisms\HandleComponents\CorruptComponentPayloadException;

return [

    /*
    |--------------------------------------------------------------------------
    | Master Switch
    |--------------------------------------------------------------------------
    |
    | Turning this off disables every Bot Shield feature at once, which is the
    | fastest way to rule the package out while debugging a production issue.
    |
    */

    'enabled' => env('BOT_SHIELD_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Bot Detector
    |--------------------------------------------------------------------------
    |
    | The driver that decides whether a request came from a bot. The default
    | "user-agent" driver treats any user agent without "Mozilla/" as a bot,
    | which is cheap and catches the scripted traffic that causes the noise.
    |
    */

    'detector' => env('BOT_SHIELD_DETECTOR', 'user-agent'),

    /*
    |--------------------------------------------------------------------------
    | Agent Rules
    |--------------------------------------------------------------------------
    |
    | Explicit user agent rules, evaluated in order before any detector runs, so
    | the first match always wins. Put your most specific rules first. Patterns
    | are case insensitive substrings unless "regex" is true.
    |
    | Actions:
    |   allow     : never a bot, so its exceptions are still reported. For
    |               uptime monitors and your own tooling.
    |   ignore    : a bot for reporting purposes, but free to browse and submit.
    |   challenge : always forced through the captcha, whatever its score.
    |   block     : refused on guarded form submissions. Page views are
    |               untouched, so search engines keep crawling normally.
    |
    */

    'agents' => [

        'rules' => [

            ['pattern' => 'Oh Dear', 'action' => 'allow'],
            ['pattern' => 'UptimeRobot', 'action' => 'allow'],
            ['pattern' => 'Better Uptime', 'action' => 'allow'],
            ['pattern' => 'Pingdom', 'action' => 'allow'],
            ['pattern' => 'StatusCake', 'action' => 'allow'],

            ['pattern' => 'Googlebot', 'action' => 'block'],
            ['pattern' => 'Storebot-Google', 'action' => 'block'],
            ['pattern' => 'Bingbot', 'action' => 'block'],
            ['pattern' => 'Applebot', 'action' => 'block'],
            ['pattern' => 'DuckDuckBot', 'action' => 'block'],
            ['pattern' => 'YandexBot', 'action' => 'block'],
            ['pattern' => 'Baiduspider', 'action' => 'block'],
            ['pattern' => 'AhrefsBot', 'action' => 'block'],
            ['pattern' => 'SemrushBot', 'action' => 'block'],

            ['pattern' => 'GPTBot', 'action' => 'block'],
            ['pattern' => 'OAI-SearchBot', 'action' => 'block'],
            ['pattern' => 'ChatGPT-User', 'action' => 'block'],
            ['pattern' => 'ClaudeBot', 'action' => 'block'],
            ['pattern' => 'Claude-Web', 'action' => 'block'],
            ['pattern' => 'anthropic-ai', 'action' => 'block'],
            ['pattern' => 'PerplexityBot', 'action' => 'block'],
            ['pattern' => 'CCBot', 'action' => 'block'],
            ['pattern' => 'Bytespider', 'action' => 'block'],
            ['pattern' => 'Amazonbot', 'action' => 'block'],
            ['pattern' => 'Meta-ExternalAgent', 'action' => 'block'],

            ['pattern' => 'curl/', 'action' => 'block'],
            ['pattern' => 'Wget/', 'action' => 'block'],
            ['pattern' => 'python-requests', 'action' => 'block'],
            ['pattern' => 'python-urllib', 'action' => 'block'],
            ['pattern' => 'aiohttp', 'action' => 'block'],
            ['pattern' => 'Go-http-client', 'action' => 'block'],
            ['pattern' => 'Java/', 'action' => 'block'],
            ['pattern' => 'Apache-HttpClient', 'action' => 'block'],
            ['pattern' => 'libwww-perl', 'action' => 'block'],
            ['pattern' => 'Scrapy', 'action' => 'block'],
            ['pattern' => 'HeadlessChrome', 'action' => 'block'],
            ['pattern' => 'PhantomJS', 'action' => 'block'],
            ['pattern' => 'Nmap', 'action' => 'block'],
            ['pattern' => 'masscan', 'action' => 'block'],
            ['pattern' => 'zgrab', 'action' => 'block'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Form Guard
    |--------------------------------------------------------------------------
    |
    | Refuses guarded form submissions, through the #[BlocksBots] Livewire
    | attribute or the ProtectsAgainstBots trait.
    |
    | "use_detector" additionally refuses anything the detector considers a bot,
    | not only agents matched by a block rule. It is off by default because the
    | default detector treats every user agent without "Mozilla/" as a bot,
    | which would also refuse legitimate API and mobile clients.
    |
    */

    'forms' => [

        'enabled' => env('BOT_SHIELD_FORM_GUARD_ENABLED', true),

        'use_detector' => env('BOT_SHIELD_FORM_GUARD_USE_DETECTOR', false),

        'status' => 403,
    ],

    /*
    |--------------------------------------------------------------------------
    | Exception Hardening
    |--------------------------------------------------------------------------
    |
    | Bots probing Livewire endpoints throw exceptions that say nothing about
    | your application. Matched exceptions on the paths below are not reported
    | when the request came from a bot, and always render as a client error
    | instead of a 500, because malformed component state is never a server
    | fault.
    |
    */

    'exceptions' => [

        'enabled' => env('BOT_SHIELD_EXCEPTIONS_ENABLED', true),

        'paths' => [
            'livewire/*',
        ],

        'status' => 422,

        /*
        | Each rule matches when the exception is an instance of "class" and
        | its message contains every needle in "contains". Add your own rules
        | here to cover new bot noise without waiting for a package release.
        */
        'rules' => [
            ['class' => CannotUpdateLockedPropertyException::class],
            ['class' => ComponentNotFoundException::class],
            ['class' => CorruptComponentPayloadException::class],
            ['class' => TypeError::class, 'contains' => ['must be of type']],
            ['class' => TypeError::class, 'contains' => ['Cannot assign', 'to property']],
            ['class' => ErrorException::class, 'contains' => ['Trying to access array offset on']],
        ],

        /*
        | Exception classes that are never reported, regardless of the request
        | or the detector verdict. Useful for third party packages that report
        | client mistakes as server errors, such as OAuth server exceptions.
        */
        'dont_report' => [],

        /*
        | Infrastructure hiccups that resolve themselves and drown out real
        | errors. Off by default: while enabled you stop seeing a database
        | genuinely falling over, so switch it on only once the noise is proven.
        */
        'transient_errors' => [

            'enabled' => env('BOT_SHIELD_TRANSIENT_ERRORS_ENABLED', false),

            'rules' => [
                ['class' => QueryException::class, 'contains' => ['server has gone away']],
                ['class' => QueryException::class, 'contains' => ['Lost connection to']],
                ['class' => QueryException::class, 'contains' => ['Connection refused']],
                ['class' => QueryException::class, 'contains' => ['Deadlock found']],
                ['class' => QueryException::class, 'contains' => ['Lock wait timeout exceeded']],
            ],
        ],

        /*
        | Never report 4xx responses thrown by your own API exception base
        | classes. Laravel already ignores its own HTTP exceptions, so this is
        | only needed for custom classes, which you must list explicitly.
        */
        'client_errors' => [

            'enabled' => env('BOT_SHIELD_CLIENT_ERRORS_ENABLED', false),

            'classes' => [],
        ],
    ],

];
