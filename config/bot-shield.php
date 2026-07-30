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
