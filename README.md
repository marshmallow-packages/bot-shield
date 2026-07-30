![marshmallow.](https://marshmallow.dev/cdn/media/logo-red-237x46.png "marshmallow.")

# Bot Shield

[![Latest Version on Packagist](https://img.shields.io/packagist/v/marshmallow/bot-shield.svg?style=flat-square)](https://packagist.org/packages/marshmallow/bot-shield)
[![Tests](https://img.shields.io/github/actions/workflow/status/marshmallow-packages/bot-shield/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/marshmallow-packages/bot-shield/actions/workflows/tests.yml)
[![PHP from Packagist](https://img.shields.io/packagist/php-v/marshmallow/bot-shield.svg?style=flat-square)](https://packagist.org/packages/marshmallow/bot-shield)
[![Total Downloads](https://img.shields.io/packagist/dt/marshmallow/bot-shield.svg?style=flat-square)](https://packagist.org/packages/marshmallow/bot-shield)

Bot traffic hardening and spam protection for Laravel + Livewire: exception noise suppression, honeypot, reCAPTCHA, crawler rules and monitoring.

Bots probing Livewire endpoints throw exceptions that say nothing about your application, and they fill your forms with spam. Bot Shield handles both from one config file: it stops the noise reaching Sentry, protects forms with a honeypot and reCAPTCHA, and records what it blocked so you can tune the thresholds against real traffic instead of guesswork.

Every feature is independently toggleable, and features that need keys disable themselves when the keys are missing. A site that only wants the exception hardening does not have to install anything else.

## Requirements

- PHP 8.3+
- Laravel 12 or 13
- Livewire 3 or 4, for the Livewire attributes only

## Installation

Install the package via Composer:

```bash
composer require marshmallow/bot-shield
```

Run the installer. It publishes the config, wires the exception handler into `bootstrap/app.php`, and adds the env keys to `.env.example`:

```bash
php artisan bot-shield:install
```

If you use the monitoring table, publish and run the migration:

```bash
php artisan vendor:publish --tag="bot-shield-migrations"
php artisan migrate
```

Then confirm the setup:

```bash
php artisan bot-shield:doctor
```

### Optional dependencies

These are suggested rather than required, so an exception-only install stays lean. The package tells you which one is missing if you enable a feature without it.

```bash
composer require spatie/laravel-honeypot    # the honeypot form fields
composer require jaybizzle/crawler-detect   # the stricter bot detector
```

### Publishing other resources

```bash
php artisan vendor:publish --tag="bot-shield-config"
php artisan vendor:publish --tag="bot-shield-views"
php artisan vendor:publish --tag="bot-shield-lang"
php artisan vendor:publish --tag="bot-shield"        # everything at once
```

## Getting started

A walkthrough from an empty install to one protected form. Each step says how to tell it worked.

**1. Install and wire.**

```bash
composer require marshmallow/bot-shield
php artisan bot-shield:install
```

Check: `php artisan bot-shield:doctor` reports `Exception hardening ... wired`. If it says `not wired`, the installer could not edit `bootstrap/app.php`; add the `withExceptions()` call from the next section by hand.

**2. Confirm the exception noise is handled.**

Nothing else is needed for this part. Bot-induced Livewire exceptions stop reaching your error tracker from now on, and matched exceptions answer a client error instead of a 500. If that is all you wanted, stop here and turn off the rest:

```dotenv
BOT_SHIELD_HONEYPOT_ENABLED=false
BOT_SHIELD_RECAPTCHA_ENABLED=false
BOT_SHIELD_MONITORING_ENABLED=false
BOT_SHIELD_RATE_LIMIT_ENABLED=false
BOT_SHIELD_PROBE_PATHS_ENABLED=false
```

**3. Turn on monitoring, so the later steps are measurable.**

```bash
php artisan vendor:publish --tag="bot-shield-migrations"
php artisan migrate
```

Then schedule pruning, or the table grows forever:

```php
Schedule::command('model:prune', [
    '--model' => [Marshmallow\BotShield\Models\BotShieldEvent::class],
])->daily();
```

Check: `bot-shield:doctor` reports `Monitoring ... recording`.

**4. Add the honeypot to one form.**

```bash
composer require spatie/laravel-honeypot
```

In the form's view:

```blade
<x-bot-shield::honeypot />
```

For a Livewire component, add `livewire-model="honeypotData"` to that tag, give the component a `public HoneypotData $honeypotData;`, initialise it in `mount()`, use the `ProtectsAgainstSpam` trait, and call `$this->protectAgainstSpam()` at the top of the submit action. Both variants are shown under [Protecting forms](#protecting-forms).

Check: view the page source and you should see a hidden `div` with two inputs. If nothing renders, the honeypot is disabled or spatie is missing, and `bot-shield:doctor` says which.

**5. Add reCAPTCHA to the same form.**

Create a key in the [reCAPTCHA admin console](https://www.google.com/recaptcha/admin/create). Pick **score based (v3)** for the default driver, or **challenge (v2)** and set `BOT_SHIELD_CAPTCHA_DRIVER=google-v2`. Add every domain the form is served from, and see [Local development](#local-development) before you paste production keys into your own `.env`. The console gives you a site key and a secret key:

```dotenv
BOT_SHIELD_RECAPTCHA_SITE_KEY=your-site-key
BOT_SHIELD_RECAPTCHA_SECRET_KEY=your-secret-key
```

In the view:

```blade
<x-bot-shield::recaptcha />
```

Then verify on submit. For Livewire, add `#[ValidatesRecaptcha]` to the action and `property="gRecaptchaResponse"` to the tag. For a classic form, add the rule to your validation, which the `ValidatesRecaptcha` trait does for you in a FormRequest.

Check: `bot-shield:doctor` reports `Captcha ... google-v3, threshold 0.6`. Submit the form once, then `php artisan bot-shield:stats` shows one captcha verification. If the component renders nothing, the keys are missing.

> Deployed sites keep their env in the Forge UI, so set both keys there as well as locally.

**6. Guard the action against known bots.**

Add `#[BlocksBots]` to a Livewire submit action, or call `protectAgainstBots()` from the `ProtectsAgainstBots` trait. This refuses agents matched by a `block` or `deny` rule, which by default covers search engines, AI crawlers and scripted clients.

Check: `bot-shield:stats` shows a blocked submission after a `curl` POST to the form.

**7. Optional, stop the scanner traffic.**

Register the probe firewall first in the global middleware stack, as shown under [Middleware](#middleware). Check: `bot-shield:stats` starts listing probe path hits within a day on any public site.

**8. After a week, tune the threshold.**

```bash
php artisan bot-shield:scores --days=7
```

It prints the score distribution from your own traffic and suggests a threshold, or says there is not enough data yet. Change `BOT_SHIELD_RECAPTCHA_SCORE` only if the histogram agrees.

Writing tests for the form you just protected? See [Testing your own application](#testing-your-own-application), which lets you script captcha answers instead of calling Google.

## Exception hardening

`bot-shield:install` wires this for you. To do it by hand, call `BotShield::handles()` inside `withExceptions()`:

```php
use Illuminate\Foundation\Configuration\Exceptions;
use Marshmallow\BotShield\Facades\BotShield;

->withExceptions(function (Exceptions $exceptions) {
    BotShield::handles($exceptions);
})
```

If your application still binds its own `App\Exceptions\Handler`, use the trait instead:

```php
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Marshmallow\BotShield\Concerns\HardensAgainstBots;

class Handler extends ExceptionHandler
{
    use HardensAgainstBots;

    public function register(): void
    {
        $this->hardenAgainstBots();
    }
}
```

Either way, the malformed-Livewire exception set is no longer reported when the request came from a bot, and real browsers still report normally. Matched exceptions render as a client error rather than a 500, because malformed component state is never a server fault.

> Two Livewire exceptions, `CorruptComponentPayloadException` and `CannotUpdateLockedPropertyException`, define their own `render()` method and answer 419 in production. Laravel consults an exception's own `render()` before any package callback, so those keep their 419. That is still a client error rather than a 500, which is the point.

Add your own patterns without waiting for a release:

```php
// config/bot-shield.php
'exceptions' => [
    'rules' => [
        ['class' => YourVendor\SomeException::class],
        ['class' => TypeError::class, 'contains' => ['must be of type']],
    ],
],
```

Each rule matches when the exception is an instance of `class` and its message contains every needle in `contains`.

## Agent rules

One ordered list of user agent rules, evaluated before any detector, so the first match always wins. This is what catches a bot spoofing a browser user agent, and what stops a monitoring service that looks like a script from being treated as one.

| Action | Reported | Page views | Form submissions | robots.txt |
| --- | --- | --- | --- | --- |
| `allow` | yes | allowed | allowed | not listed |
| `ignore` | no | allowed | allowed | not listed |
| `challenge` | no | allowed | captcha forced | not listed |
| `block` | no | allowed | refused | not listed |
| `deny` | no | refused* | refused | `Disallow: /` |

\* Only once `crawlers.deny_page_views` is on and the `DenyAgents` middleware is registered.

Search engines ship as `block` rather than `deny` on purpose: they must keep crawling your pages, they just have no business submitting a form. AI crawlers ship as `deny`.

```php
'agents' => [
    'rules' => [
        ['pattern' => 'Oh Dear', 'action' => 'allow'],
        ['pattern' => 'Googlebot', 'action' => 'block'],
        ['pattern' => 'GPTBot', 'action' => 'deny'],
        ['pattern' => '/^curl\/[0-9]+/', 'action' => 'block', 'regex' => true],
    ],
],
```

Patterns are case-insensitive substrings unless `regex` is true. A malformed rule is discarded rather than allowed to break request handling, and `bot-shield:doctor` reports any it had to drop.

### Detector drivers

`user-agent` (the default) treats any user agent without `Mozilla/` as a bot. `crawler-detect` matches against crawler-detect's maintained list instead. You can also name your own class implementing `Marshmallow\BotShield\Contracts\BotDetector`.

```php
'detector' => env('BOT_SHIELD_DETECTOR', 'user-agent'),
```

## Protecting forms

### Livewire

```php
use Livewire\Component;
use Marshmallow\BotShield\Livewire\BlocksBots;
use Marshmallow\BotShield\Livewire\RateLimitsSubmissions;
use Marshmallow\BotShield\Livewire\ValidatesRecaptcha;

class ContactForm extends Component
{
    public string $gRecaptchaResponse = '';

    #[BlocksBots]
    #[ValidatesRecaptcha]
    #[RateLimitsSubmissions(attempts: 3, seconds: 60)]
    public function submit(): void
    {
        // Only runs once every check passes.
    }
}
```

In the component's view:

```blade
<form wire:submit="submit">
    <x-bot-shield::honeypot livewire-model="honeypotData" />
    <x-bot-shield::recaptcha property="gRecaptchaResponse" />

    <button type="submit">Send</button>
</form>
```

For components that cannot use attributes, the equivalent traits are available:

```php
use Marshmallow\BotShield\Concerns\ProtectsAgainstBots;
use Marshmallow\BotShield\Concerns\ProtectsAgainstSpam;
use Spatie\Honeypot\Http\Livewire\Concerns\HoneypotData;

class ContactForm extends Component
{
    use ProtectsAgainstBots;
    use ProtectsAgainstSpam;

    public HoneypotData $honeypotData;

    public function mount(): void
    {
        $this->honeypotData = new HoneypotData;
    }

    public function submit(): void
    {
        $this->protectAgainstBots();
        $this->protectAgainstSpam();
    }
}
```

### Classic forms

```blade
<form method="POST" action="/contact">
    @csrf

    <x-bot-shield::honeypot />
    <x-bot-shield::recaptcha />

    <button type="submit">Send</button>
</form>
```

Validate with the rule, or let the trait add it for you:

```php
use Illuminate\Foundation\Http\FormRequest;
use Marshmallow\BotShield\Concerns\ValidatesRecaptcha;

class ContactRequest extends FormRequest
{
    use ValidatesRecaptcha;

    public function rules(): array
    {
        return array_merge($this->recaptchaRules('contact'), [
            'email' => ['required', 'email'],
        ]);
    }
}
```

The trait adds nothing while no captcha is active, so a site without keys is never asked for a token it never rendered. Using the rule directly works too:

```php
use Marshmallow\BotShield\Rules\Recaptcha;

'g-recaptcha-response' => [new Recaptcha],
```

The rule is implicit, so it runs even when the field is absent. A scripted submission that simply omits the token is refused rather than skipped.

## reCAPTCHA

Keys come from the [reCAPTCHA admin console](https://www.google.com/recaptcha/admin/create): choose score based (v3) for the default driver, or challenge (v2) with `BOT_SHIELD_CAPTCHA_DRIVER=google-v2`. The domain list on the key has to include every host that serves the form.

Set the keys and you are done. Leave them empty and the captcha disables itself: nothing is rendered and nothing is verified.

```dotenv
BOT_SHIELD_RECAPTCHA_SITE_KEY=
BOT_SHIELD_RECAPTCHA_SECRET_KEY=
BOT_SHIELD_CAPTCHA_DRIVER=google-v3
BOT_SHIELD_RECAPTCHA_SCORE=0.6
```

Drivers are `google-v3` (score based, invisible), `google-v2` (checkbox or invisible challenge) and `null`. The component and the `@botShieldRecaptcha` directive render whichever applies:

```blade
<x-bot-shield::recaptcha />
<x-bot-shield::recaptcha size="invisible" theme="dark" locale="nl" />

@botShieldRecaptcha
@botShieldRecaptcha(['size' => 'invisible'])
```

Set `show_terms` to render Google's required notice when you hide the badge.

Every verification is logged and recorded with its score, pass or fail. That is what makes the threshold reviewable, see `bot-shield:scores` below.

When Google cannot be reached the submission is refused, because an outage should not become an open door. Set `fail_open` to `true` if losing genuine leads is the worse cost for your site.

### Local development

Simplest: leave both keys out of your local `.env`. The captcha needs a site key and a secret key before it does anything, so without them the component renders nothing, `verify()` returns skipped, and your forms submit exactly as they would with the captcha switched off. No local override needed.

Do not copy production keys into a local `.env`. A key only works on the domains listed against it, so on an unlisted host Google refuses to solve the challenge client side, the token field stays empty, and the submission is refused as a missing token. The form looks broken and nothing in the logs blames the domain. `bot-shield:doctor` cannot catch this either: it sees two keys and reports the captcha as active.

If you do want to exercise the real thing locally, add `localhost` and your local domain, `example.test` and the like, to the key's domain list in the console. A separate key for local work keeps that list off the production key.

To switch it off explicitly instead, per environment:

```dotenv
BOT_SHIELD_RECAPTCHA_ENABLED=false
```

Or leave the captcha enabled and drop it to the null driver with `BOT_SHIELD_CAPTCHA_DRIVER=null`. Every other part of the package keeps working either way.

For automated tests, do not disable it. `BotShield::fake()` scripts the answers without calling Google, see [Testing your own application](#testing-your-own-application).

## Middleware

Neither is registered for you. The probe firewall belongs first in the global stack; refusing crawler page views is a decision per site.

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->prepend(\Marshmallow\BotShield\Http\Middleware\BlockProbePaths::class);
    // $middleware->append(\Marshmallow\BotShield\Http\Middleware\DenyAgents::class);
})
```

Aliases `bot-shield.probe-paths` and `bot-shield.deny-agents` are also registered for use on route groups.

`BlockProbePaths` answers known scanner paths (`wp-login.php`, `.env`, `xmlrpc.php` and friends) with an immediate response. It returns rather than aborts, so there is no exception to report and no session to start.

## robots.txt

Turn every `deny` rule into robots.txt stanzas:

```bash
php artisan bot-shield:robots
php artisan bot-shield:robots --append=public/robots.txt
```

Search engines are never emitted, because they are `block` rather than `deny`.

## Monitoring

One row per notable event in `bot_shield_events`: captcha verifications with their score, honeypot trips, blocked submissions, suppressed exceptions and probe path hits.

```bash
php artisan bot-shield:stats --days=7
php artisan bot-shield:scores --days=30 --form=contact
```

`bot-shield:scores` prints the score distribution and suggests a threshold from the widest quiet stretch between the clusters in your traffic. It refuses to suggest anything below 50 samples, and says so when the scores do not separate, rather than inventing a number.

Recording is best effort and swallows its own failures. It runs inside form submissions and inside the exception handler, so a missing table never becomes an outage; the structured log channel stays the durable record either way.

Rows are pruned with Laravel's `model:prune`, so schedule it:

```php
Schedule::command('model:prune', [
    '--model' => [Marshmallow\BotShield\Models\BotShieldEvent::class],
])->daily();
```

## Testing your own application

```php
use Marshmallow\BotShield\Facades\BotShield;

$shield = BotShield::fake();

$shield->captchaPasses(0.9);   // or captchaFails(), captchaScores(0.2), captchaUnavailable()

// ... exercise your form ...

$shield->assertChallenged();
$shield->assertCaptchaPassed();
$shield->assertBlocked('contact');
$shield->assertNotBlocked();
$shield->assertHoneypotTripped();
$shield->assertRateLimited();
```

Captcha answers are scripted, so nothing reaches Google, and events are kept in memory rather than the database. A blank token still fails under the fake, because that bypass is real behaviour rather than a stub detail.

To take the package out of the way entirely for tests that are about something else:

```php
BotShield::fake()->withoutProtection();
```

## Extending

Ask whether the current request looks like a bot anywhere in your own code. Agent rules are applied before the detector, so this answers with the same verdict the rest of the package uses:

```php
use Marshmallow\BotShield\Facades\BotShield;

if (BotShield::isBot()) {
    // Skip the expensive thing, or the analytics hit.
}

BotShield::isBot($request);      // or pass a specific request
BotShield::detector();           // the resolved detector, rules included
```

Both moving parts are swappable by naming your class in config. A detector implements `Marshmallow\BotShield\Contracts\BotDetector`:

```php
use Illuminate\Http\Request;
use Marshmallow\BotShield\Contracts\BotDetector;

class IpRangeDetector implements BotDetector
{
    public function isBot(Request $request): bool
    {
        return YourIpRanges::contains($request->ip());
    }
}
```

```php
'detector' => \App\BotShield\IpRangeDetector::class,
```

A captcha driver implements `Marshmallow\BotShield\Contracts\CaptchaDriver` and returns a `CaptchaVerdict`, which is how a provider other than reCAPTCHA (Cloudflare Turnstile, for instance) can be added without touching the rest of the package:

```php
'captcha' => [
    'driver' => \App\BotShield\TurnstileDriver::class,
],
```

Your class is resolved from the container, so it can take constructor dependencies. Both are validated at resolve time and report a clear error through `bot-shield:doctor` if they do not implement the contract.

## Features and defaults

Nothing here is mandatory. Every feature can be turned off, and `BOT_SHIELD_ENABLED=false` turns off all of them at once. "Default" is what a fresh install does with no config changes.

Two features read as on but do nothing until you act: exception hardening needs `BotShield::handles()` called, and the probe firewall needs its middleware registered. `bot-shield:doctor` catches the first.

| Feature | Default | Also needs | Toggle |
| --- | --- | --- | --- |
| Livewire exception suppression | on | `BotShield::handles()` or the trait | `BOT_SHIELD_EXCEPTIONS_ENABLED` |
| Transient database error suppression | **off** | | `BOT_SHIELD_TRANSIENT_ERRORS_ENABLED` |
| Custom 4xx suppression | **off** | `exceptions.client_errors.classes` | `BOT_SHIELD_CLIENT_ERRORS_ENABLED` |
| Agent allow and block rules | on, ~40 rules | | `agents.rules`, config only |
| Form guard | on | `#[BlocksBots]` on an action | `BOT_SHIELD_FORM_GUARD_ENABLED` |
| Refuse everything the detector flags | **off** | | `BOT_SHIELD_FORM_GUARD_USE_DETECTOR` |
| Submission rate limiting | on | `#[RateLimitsSubmissions]` on an action | `BOT_SHIELD_RATE_LIMIT_ENABLED` |
| reCAPTCHA | on, inert without keys | the keys, and the blade component | `BOT_SHIELD_RECAPTCHA_ENABLED` |
| Accept submissions during a captcha outage | **off** | | `BOT_SHIELD_RECAPTCHA_FAIL_OPEN` |
| Google terms notice | **off** | | `BOT_SHIELD_RECAPTCHA_SHOW_TERMS` |
| Honeypot | on | `spatie/laravel-honeypot`, and the blade component | `BOT_SHIELD_HONEYPOT_ENABLED` |
| Honeypot config driven from this package | on | | `BOT_SHIELD_HONEYPOT_MANAGE_CONFIG` |
| Probe path firewall | on | the `BlockProbePaths` middleware registered | `BOT_SHIELD_PROBE_PATHS_ENABLED` |
| Refuse denied crawlers' page views | **off** | the `DenyAgents` middleware registered | `BOT_SHIELD_DENY_PAGE_VIEWS` |
| robots.txt stanzas | on demand | | none, run `bot-shield:robots` |
| Event recording | on | the migration published and run | `BOT_SHIELD_MONITORING_ENABLED` |
| Hashed addresses in the events table | **off** | | `BOT_SHIELD_MONITORING_HASH_IPS` |
| Structured captcha logging | on | | `BOT_SHIELD_CAPTCHA_LOG` |

The optional Composer packages are only needed by the feature that uses them: `spatie/laravel-honeypot` for the honeypot, `jaybizzle/crawler-detect` for that detector driver, and `livewire/livewire` for the Livewire attributes. Enable a feature without its dependency and the package tells you which command to run rather than failing with a class-not-found.

### Exception hardening only

The leanest useful install. No extra Composer packages, no migration, no middleware:

```dotenv
BOT_SHIELD_HONEYPOT_ENABLED=false
BOT_SHIELD_RECAPTCHA_ENABLED=false
BOT_SHIELD_MONITORING_ENABLED=false
BOT_SHIELD_RATE_LIMIT_ENABLED=false
BOT_SHIELD_PROBE_PATHS_ENABLED=false
```

## Configuration

Full documentation lives in the published `config/bot-shield.php`. The top-level keys:

| Key | Default | Description |
| --- | --- | --- |
| `enabled` | `true` | Master switch. Turns every feature off at once. |
| `detector` | `user-agent` | Detector driver, or a `BotDetector` class name. |
| `agents.rules` | ~40 rules | Ordered user agent rules, first match wins. |
| `forms.enabled` | `true` | Whether the form guard runs at all. |
| `forms.use_detector` | `false` | Also refuse anything the detector flags, not only `block` rules. |
| `probe_paths.enabled` | `true` | Whether `BlockProbePaths` answers scanner paths. |
| `probe_paths.paths` | scanner list | Paths answered immediately. |
| `rate_limit.attempts` | `5` | Submissions allowed per address per form. |
| `rate_limit.decay_seconds` | `60` | Window for the above. |
| `crawlers.deny_page_views` | `false` | Whether `DenyAgents` refuses `deny` agents. |
| `monitoring.enabled` | `true` | Whether events are recorded to the table. |
| `monitoring.hash_ips` | `false` | Store a SHA-256 of the address instead of the address. |
| `monitoring.retention_days` | `30` | Pruning window for `model:prune`. |
| `honeypot.enabled` | `true` | Whether the honeypot fields render and are checked. |
| `honeypot.manage_spatie_config` | `true` | Drive spatie's config from this file. |
| `honeypot.seconds` | `2` | Minimum seconds between rendering and submitting. |
| `captcha.enabled` | `true` | Whether tokens are verified. |
| `captcha.driver` | `google-v3` | `google-v3`, `google-v2` or `null`. |
| `captcha.score` | `0.6` | Minimum v3 score to accept. |
| `captcha.score_challenge` | `0.9` | Stricter threshold for `challenge` agents. |
| `captcha.fail_open` | `false` | Accept submissions when the provider is unreachable. |
| `captcha.log` | `true` | Log every verification with its score. |
| `captcha.log_channel` | app default | Channel the above writes to. |
| `exceptions.enabled` | `true` | Whether exception hardening is active. |
| `exceptions.paths` | `livewire/*` | Paths the hardening applies to. |
| `exceptions.rules` | Livewire set | Exception matchers, extensible per site. |

Two notes worth reading before you deploy:

- **`honeypot.manage_spatie_config`** pushes this package's honeypot settings into spatie's config at boot, which is the point: one file per site instead of a published `honeypot.php` drifting per project. If you already maintain `config/honeypot.php` by hand, set it to `false`.
- **`monitoring.hash_ips`** keeps grouping and counting working, so repeat offenders are still visible, but you lose the ability to read an address off and act on it. Consider it if storing visitor addresses for a month is more than your privacy notice covers.

## Artisan commands

| Command | Purpose |
| --- | --- |
| `bot-shield:install` | Publish config, wire the handler, add env keys. |
| `bot-shield:doctor` | Check the wiring and configuration. |
| `bot-shield:stats` | Summarise what was blocked and suppressed. |
| `bot-shield:scores` | Score distribution and a suggested threshold. |
| `bot-shield:robots` | robots.txt stanzas for denied agents. |

## Translations

English and Dutch ship with the package. Publish them to change the wording:

```bash
php artisan vendor:publish --tag="bot-shield-lang"
```

## Testing

```bash
composer test
```

That runs PHPStan, Pint, a 100% type coverage check and the Pest suite. Individual steps are available as `composer analyse`, `composer lint:check`, `composer test:types` and `composer test:unit`.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for recent changes.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please report security vulnerabilities by email to [lars@marshmallow.dev](mailto:lars@marshmallow.dev) rather than via the public issue tracker.

## Credits

- [Marshmallow](https://github.com/marshmallow-packages)
- [All Contributors](https://github.com/marshmallow-packages/bot-shield/contributors)

## License

The MIT License. Please see the [License File](LICENSE.md) for more information.
