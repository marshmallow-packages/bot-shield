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
    |   deny      : not welcome at all. Refused on form submissions, listed as
    |               Disallow by bot-shield:robots, and refused on page views once
    |               the DenyAgents middleware is registered.
    |
    | Search engines default to "block" rather than "deny" on purpose: they must
    | keep crawling pages, they just have no business submitting a form.
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

            ['pattern' => 'GPTBot', 'action' => 'deny'],
            ['pattern' => 'OAI-SearchBot', 'action' => 'deny'],
            ['pattern' => 'ChatGPT-User', 'action' => 'deny'],
            ['pattern' => 'ClaudeBot', 'action' => 'deny'],
            ['pattern' => 'Claude-Web', 'action' => 'deny'],
            ['pattern' => 'anthropic-ai', 'action' => 'deny'],
            ['pattern' => 'PerplexityBot', 'action' => 'deny'],
            ['pattern' => 'CCBot', 'action' => 'deny'],
            ['pattern' => 'Bytespider', 'action' => 'deny'],
            ['pattern' => 'Amazonbot', 'action' => 'deny'],
            ['pattern' => 'Meta-ExternalAgent', 'action' => 'deny'],

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
    | Probe Path Firewall
    |--------------------------------------------------------------------------
    |
    | Scanner paths answered immediately by the BlockProbePaths middleware, which
    | you register first in the global stack. It returns a response rather than
    | aborting, so there is no exception to report and no session to start.
    |
    */

    'probe_paths' => [

        'enabled' => env('BOT_SHIELD_PROBE_PATHS_ENABLED', true),

        'status' => 404,

        'paths' => [
            'wp-login.php',
            'wp-admin',
            'wp-admin/*',
            'wp-content/*',
            'wp-includes/*',
            'wordpress/*',
            'xmlrpc.php',
            '.env',
            '.env.*',
            '.git',
            '.git/*',
            '.aws/*',
            'phpinfo.php',
            'phpmyadmin',
            'phpmyadmin/*',
            'pma/*',
            'vendor/phpunit/*',
            'config.json',
            'credentials',
            '.well-known/security.txt.bak',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Submission Rate Limit
    |--------------------------------------------------------------------------
    |
    | Applied through the #[RateLimitsSubmissions] Livewire attribute or the
    | SubmissionLimiter service. Counts per address per form, so one noisy client
    | cannot flood a single form.
    |
    */

    'rate_limit' => [

        'enabled' => env('BOT_SHIELD_RATE_LIMIT_ENABLED', true),

        'attempts' => env('BOT_SHIELD_RATE_LIMIT_ATTEMPTS', 5),

        'decay_seconds' => env('BOT_SHIELD_RATE_LIMIT_DECAY', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Crawler Management
    |--------------------------------------------------------------------------
    |
    | bot-shield:robots turns every "deny" agent rule into robots.txt stanzas.
    | That is the courteous mechanism and most crawlers honour it, so refusing
    | their page views outright is off by default. Turn it on and register the
    | DenyAgents middleware for the ones that ignore robots.txt.
    |
    */

    'crawlers' => [

        'deny_page_views' => env('BOT_SHIELD_DENY_PAGE_VIEWS', false),

        'status' => 403,
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring
    |--------------------------------------------------------------------------
    |
    | Records one row per notable event in bot_shield_events, which is what makes
    | "is 0.6 too strict for this audience" answerable from real traffic. Rows
    | are pruned by Laravel's model:prune command, so schedule it.
    |
    | Recording is best effort: it runs inside form submissions and inside the
    | exception handler, so a missing table never becomes an outage. The
    | structured log channel remains the durable record either way.
    |
    | "hash_ips" stores a SHA-256 of the address instead of the address itself.
    | Grouping and counting still work, so repeat offenders are still visible,
    | but you lose the ability to read an address off and act on it. Consider it
    | if storing visitor IPs for 30 days is more than your privacy notice covers.
    |
    */

    'monitoring' => [

        'enabled' => env('BOT_SHIELD_MONITORING_ENABLED', true),

        'hash_ips' => env('BOT_SHIELD_MONITORING_HASH_IPS', false),

        'retention_days' => env('BOT_SHIELD_MONITORING_RETENTION_DAYS', 30),

        'connection' => env('BOT_SHIELD_MONITORING_CONNECTION'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Honeypot
    |--------------------------------------------------------------------------
    |
    | Hidden form fields that a person never fills and a scripted submission
    | usually does, plus a minimum time on the page. Requires
    | spatie/laravel-honeypot, which is a suggested rather than a required
    | dependency so exception only installs stay lean.
    |
    | While "manage_spatie_config" is true the values below are pushed into
    | spatie's own config at boot, which is the point of this package: one file
    | per site instead of a published honeypot.php drifting per project. Turn it
    | off to manage config/honeypot.php yourself.
    |
    */

    'honeypot' => [

        'enabled' => env('BOT_SHIELD_HONEYPOT_ENABLED', true),

        'manage_spatie_config' => env('BOT_SHIELD_HONEYPOT_MANAGE_CONFIG', true),

        /*
        | Avoid spatie's well known "my_name" default, which bots recognise, and
        | pick something that reads like a real field on your forms.
        */
        'name_field' => env('BOT_SHIELD_HONEYPOT_NAME', 'contact_reference'),

        'randomize_name_field' => env('BOT_SHIELD_HONEYPOT_RANDOMIZE', true),

        'valid_from_field' => env('BOT_SHIELD_HONEYPOT_VALID_FROM', 'submitted_from'),

        /*
        | Minimum seconds between rendering the form and submitting it. Two is a
        | little stricter than spatie's default of one and still well under the
        | time a person needs to fill in even a short contact form.
        */
        'seconds' => env('BOT_SHIELD_HONEYPOT_SECONDS', 2),

        'status' => 403,
    ],

    /*
    |--------------------------------------------------------------------------
    | Captcha
    |--------------------------------------------------------------------------
    |
    | Drivers: "google-v3" (score based, invisible), "google-v2" (checkbox or
    | invisible challenge), or "null" for sites protected by the honeypot and
    | detector alone.
    |
    | The captcha disables itself while the keys are missing, so a site without
    | keys is never asked for a token it cannot produce. That is also how local
    | development stays simple: leave the keys out rather than copying the
    | production pair, which Google will not solve on an unlisted domain.
    | "score_challenge" is the stricter threshold applied to agents matched by
    | a challenge rule.
    |
    | "fail_open" decides what happens when the provider cannot be reached. It
    | is off by default, so an outage refuses submissions rather than waving
    | every bot through; turn it on if losing genuine leads is the worse cost.
    |
    */

    'captcha' => [

        'enabled' => env('BOT_SHIELD_RECAPTCHA_ENABLED', true),

        'driver' => env('BOT_SHIELD_CAPTCHA_DRIVER', 'google-v3'),

        'site_key' => env('BOT_SHIELD_RECAPTCHA_SITE_KEY'),

        'secret_key' => env('BOT_SHIELD_RECAPTCHA_SECRET_KEY'),

        'score' => env('BOT_SHIELD_RECAPTCHA_SCORE', 0.6),

        'score_challenge' => env('BOT_SHIELD_RECAPTCHA_SCORE_CHALLENGE', 0.9),

        'timeout' => env('BOT_SHIELD_RECAPTCHA_TIMEOUT', 5),

        'connect_timeout' => env('BOT_SHIELD_RECAPTCHA_CONNECT_TIMEOUT', 3),

        'fail_open' => env('BOT_SHIELD_RECAPTCHA_FAIL_OPEN', false),

        /*
        | An unreachable provider refuses every submission, so it is worth
        | knowing about, but a real outage means one report per attempt across
        | every form. Off by default for that reason. Turn it on if your site
        | reported captcha failures to Sentry before adopting this package.
        */
        'report_outages' => env('BOT_SHIELD_RECAPTCHA_REPORT_OUTAGES', false),

        /*
        | Every verification is logged with its score, which is how a threshold
        | gets tuned against real traffic. Turn "log" off for a site that keeps
        | its record in the events table alone, or where the log volume is not
        | worth it. "log_channel" is empty for the application default.
        */
        'log' => env('BOT_SHIELD_CAPTCHA_LOG', true),

        'log_channel' => env('BOT_SHIELD_LOG_CHANNEL'),

        'field' => 'g-recaptcha-response',

        'theme' => env('BOT_SHIELD_RECAPTCHA_THEME', 'light'),

        'size' => env('BOT_SHIELD_RECAPTCHA_SIZE', 'normal'),

        'locale' => env('BOT_SHIELD_RECAPTCHA_LOCALE'),

        /*
        | The v3 and invisible widgets park a badge in the corner of the page.
        | Hiding it is allowed only when the notice below is visible instead, so
        | "hide_badge" turns "show_terms" on for you. The badge is hidden rather
        | than removed, because a display of none stops the widget working.
        |
        | "badge" moves it instead of hiding it: "bottomright", "bottomleft" or
        | "inline" to place it in the flow of the form yourself. It reaches the
        | invisible v2 widget only, since v3 gives no control over the position.
        */
        'hide_badge' => env('BOT_SHIELD_RECAPTCHA_HIDE_BADGE', false),

        'badge' => env('BOT_SHIELD_RECAPTCHA_BADGE', 'bottomright'),

        'show_terms' => env('BOT_SHIELD_RECAPTCHA_SHOW_TERMS', false),
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
