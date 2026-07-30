---
name: bot-shield-development
description: >
  Configure and apply the Bot Shield package in Laravel applications:
  Livewire exception noise suppression, agent allow and block rules,
  honeypot, reCAPTCHA v2 and v3, probe path firewall, submission rate
  limiting, and blocked traffic monitoring.
license: MIT
metadata:
  author: LTKort
---

# Bot Shield

Use this skill when a Laravel application needs to integrate `marshmallow/bot-shield`.

## Primary Goal

- apply the `marshmallow/bot-shield` package's public API in the smallest correct way
- turn on only the features the app actually needs, since every one is independently toggleable

## Workflow

### 1. Inspect the app context

- confirm the app is a Laravel project on PHP 8.3+ and Laravel 12 or 13
- check whether `livewire/livewire` is installed; the Livewire attributes need it, nothing else does
- check whether exceptions are configured in `bootstrap/app.php` via `withExceptions()`, or the app still binds its own `App\Exceptions\Handler`
- find the forms to protect, and whether they are Livewire components or classic POST forms

### 2. Install and wire

```bash
composer require marshmallow/bot-shield
php artisan bot-shield:install
php artisan bot-shield:doctor
```

`bot-shield:install` publishes the config, wires the exception handler into `bootstrap/app.php`, and adds env keys to `.env.example`. It reports what it could not do rather than guessing. `bot-shield:doctor` verifies the result and is the fastest way to find a misconfiguration.

Wire by hand only if the installer could not:

```php
use Illuminate\Foundation\Configuration\Exceptions;
use Marshmallow\BotShield\Facades\BotShield;

->withExceptions(function (Exceptions $exceptions) {
    BotShield::handles($exceptions);
})
```

For an app that still binds its own handler, use the trait in `register()` instead:

```php
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

Only install the optional dependencies for features the app enables:

```bash
composer require spatie/laravel-honeypot    # honeypot only
composer require jaybizzle/crawler-detect   # the crawler-detect driver only
```

### 3. Protect Livewire forms

Attributes stop the action before it runs. Not calling them leaves the action untouched.

```php
use Marshmallow\BotShield\Livewire\BlocksBots;
use Marshmallow\BotShield\Livewire\RateLimitsSubmissions;
use Marshmallow\BotShield\Livewire\ValidatesRecaptcha;

class ContactForm extends Component
{
    public string $gRecaptchaResponse = '';

    #[BlocksBots]
    #[ValidatesRecaptcha]
    #[RateLimitsSubmissions(attempts: 3, seconds: 60)]
    public function submit(): void {}
}
```

- `#[ValidatesRecaptcha(property: 'gRecaptchaResponse', form: null)]` reads the token from that component property, falling back to the request payload.
- `#[RateLimitsSubmissions(attempts: null, seconds: null, form: null)]` uses the configured defaults when the arguments are omitted.

In the view, name the property so the token syncs back:

```blade
<x-bot-shield::honeypot livewire-model="honeypotData" />
<x-bot-shield::recaptcha property="gRecaptchaResponse" />
```

The honeypot component needs a `HoneypotData` property and the spam trait:

```php
use Marshmallow\BotShield\Concerns\ProtectsAgainstSpam;
use Spatie\Honeypot\Http\Livewire\Concerns\HoneypotData;

use ProtectsAgainstSpam;

public HoneypotData $honeypotData;

public function mount(): void
{
    $this->honeypotData = new HoneypotData;
}

public function submit(): void
{
    $this->protectAgainstSpam();
}
```

`Marshmallow\BotShield\Concerns\ProtectsAgainstBots` provides `protectAgainstBots()` as the equivalent of `#[BlocksBots]` for components that cannot use attributes.

### 4. Protect classic forms

```blade
<x-bot-shield::honeypot />
<x-bot-shield::recaptcha />
```

`@botShieldRecaptcha` and `@botShieldRecaptcha(['size' => 'invisible'])` render the same widget.

Validate with the rule, or let the trait add it:

```php
use Marshmallow\BotShield\Concerns\ValidatesRecaptcha;
use Marshmallow\BotShield\Rules\Recaptcha;

// In a FormRequest, with the trait:
return array_merge($this->recaptchaRules('contact'), [
    'email' => ['required', 'email'],
]);

// Or directly:
'g-recaptcha-response' => [new Recaptcha('contact')];
```

`recaptchaRules()` returns nothing while no captcha is active, so an app without keys is never asked for a token it never rendered. The rule is implicit and runs even when the field is absent.

### 5. Configure reCAPTCHA

```dotenv
BOT_SHIELD_RECAPTCHA_SITE_KEY=
BOT_SHIELD_RECAPTCHA_SECRET_KEY=
BOT_SHIELD_CAPTCHA_DRIVER=google-v3
BOT_SHIELD_RECAPTCHA_SCORE=0.6
```

Drivers are `google-v3`, `google-v2` and `null`. Leaving the keys empty disables the captcha: nothing renders and nothing is verified, so a site without keys is never punished. Set `captcha.hide_badge` to hide the corner badge, which also renders Google's required notice because hiding it without the notice breaks their terms; `captcha.show_terms` renders that notice on its own. `captcha.badge` moves the badge instead of hiding it, `bottomright`, `bottomleft` or `inline`, and reaches the invisible v2 widget only. All three can be overridden per tag with `hide-badge` and `badge`.

When the provider is unreachable the submission is refused. Set `captcha.fail_open` to `true` only if losing genuine leads is the worse cost for that site.

### 6. Add agent rules

One ordered list in `config/bot-shield.php`, evaluated before any detector, first match wins:

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

- `allow`: never a bot, so its exceptions are still reported. Uptime monitors and own tooling.
- `ignore`: a bot for reporting purposes, free to browse and submit.
- `challenge`: forced through the captcha at the stricter `captcha.score_challenge` threshold.
- `block`: refused on guarded form submissions, page views untouched.
- `deny`: also listed by `bot-shield:robots`, and refused on page views once the `DenyAgents` middleware is registered.

Keep search engines on `block`, not `deny`, so they keep crawling pages while never submitting a form.

Patterns are case-insensitive substrings unless `regex` is true. A malformed rule is discarded rather than allowed to break requests, and `bot-shield:doctor` reports any it dropped.

### 7. Register middleware when wanted

Neither is applied automatically.

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->prepend(\Marshmallow\BotShield\Http\Middleware\BlockProbePaths::class);
    // $middleware->append(\Marshmallow\BotShield\Http\Middleware\DenyAgents::class);
})
```

Aliases `bot-shield.probe-paths` and `bot-shield.deny-agents` are available for route groups. `BlockProbePaths` belongs first in the global stack; it answers scanner paths with a response rather than an abort, so there is no exception to report.

### 8. Enable monitoring

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

Read the data with `bot-shield:stats --days=7` and `bot-shield:scores --days=30 --form=contact`. `bot-shield:scores` suggests a threshold from the app's own traffic, and declines to suggest one below 50 samples.

Set `monitoring.hash_ips` to `true` where storing visitor addresses for a month exceeds what the privacy notice covers. Counting and grouping still work.

Monitoring is optional like everything else. `BOT_SHIELD_MONITORING_ENABLED=false` stops all recording, no migration needed, and nothing else in the package depends on it. Only `bot-shield:stats` and `bot-shield:scores` need it, and they warn rather than fail. `BOT_SHIELD_CAPTCHA_LOG=false` separately silences the per-verification log line for a site that wants neither.

### 9. Test the consuming app

```php
use Marshmallow\BotShield\Facades\BotShield;

$shield = BotShield::fake();
$shield->captchaPasses(0.9);

// exercise the form

$shield->assertChallenged();
$shield->assertBlocked('contact');
```

Available on the fake: `captchaPasses()`, `captchaFails()`, `captchaScores()`, `captchaUnavailable()`, `withoutProtection()`, `recorded()`, and the assertions `assertBlocked()`, `assertNotBlocked()`, `assertChallenged()`, `assertNotChallenged()`, `assertCaptchaPassed()`, `assertCaptchaFailed()`, `assertHoneypotTripped()`, `assertRateLimited()`, `assertNothingRecorded()`.

Nothing reaches Google and events are kept in memory, so no table is needed. A blank token still fails under the fake, because that bypass is real behaviour. Use `BotShield::fake()->withoutProtection()` to take the package out of the way in tests about something else.

## Rules, References, and Templates

Read before executing:

- the published `config/bot-shield.php`, which documents every key inline
- `README.md` in the package for the full config table and command list

Publish tags: `bot-shield-config`, `bot-shield-migrations`, `bot-shield-views`, `bot-shield-lang`, and `bot-shield` for all of them.

Facade methods: `BotShield::handles()`, `BotShield::isBot()`, `BotShield::detector()`, `BotShield::fake()`.

Swappable contracts: `Marshmallow\BotShield\Contracts\BotDetector` (name the class in `detector`), `Marshmallow\BotShield\Contracts\CaptchaDriver` (name the class in `captcha.driver`).

Commands: `bot-shield:install`, `bot-shield:doctor`, `bot-shield:stats`, `bot-shield:scores`, `bot-shield:robots`.

## Examples

- An app that only wants the Sentry noise gone: require the package, run `bot-shield:install`, set `BOT_SHIELD_HONEYPOT_ENABLED=false` and `BOT_SHIELD_RECAPTCHA_ENABLED=false`, install nothing else.
- A contact form drowning in spam: add `#[BlocksBots]` and `#[ValidatesRecaptcha]` to the submit action, add both blade components to the view, set the reCAPTCHA keys, then read `bot-shield:scores` after a week and tune `BOT_SHIELD_RECAPTCHA_SCORE`.
- An app being scanned for `wp-login.php`: prepend `BlockProbePaths`, then confirm with `bot-shield:stats` that the probe path hits are being answered.
- An app that wants AI crawlers out: keep the AI crawler rules on `deny`, run `bot-shield:robots --append=public/robots.txt`, and register `DenyAgents` with `crawlers.deny_page_views=true` only for the crawlers that ignore robots.txt.

## Anti-patterns

- do not document package internals here; keep the skill focused on adoption in Laravel apps
- do not set `deny` on a search engine, which removes it from the index; use `block` so it crawls but cannot submit
- do not enable `forms.use_detector` with the default `user-agent` driver unless legitimate API and mobile clients are known to send a browser user agent, since it refuses everything without `Mozilla/`
- do not expect a uniform 422 from every Livewire exception: two of them define their own `render()` and answer 419, which is still a client error rather than a 500
- do not add `MethodNotAllowedHttpException`, `NotFoundHttpException` or `TokenMismatchException` to `exceptions.rules`; Laravel already never reports them, and matching them only converts legitimate 404, 405 and 419 responses into 422s
- do not leave `honeypot.manage_spatie_config` on while also maintaining `config/honeypot.php` by hand, since the package overwrites the keys it owns at boot
- do not rely on the events table without scheduling `model:prune`
