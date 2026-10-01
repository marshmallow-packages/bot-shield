# Release Notes

## [Unreleased](https://github.com/marshmallow-packages/bot-shield/compare/v1.1.1...HEAD)

## [v1.1.1](https://github.com/marshmallow-packages/bot-shield/compare/v1.1.0...v1.1.1) - 2026-10-01

### Fixed

- The v3 widget fetches a fresh token after every Livewire round trip of its component. A token is single use, so a visitor who corrected a validation error and submitted again resent the spent token, was refused as a robot, and after a few retries hit the rate limit.
- `#[RateLimitsSubmissions]` no longer counts a submit that `#[ValidatesRecaptcha]` refused for a spent or expired token (`timeout-or-duplicate`). Low scores and invalid tokens still count. The count now happens after the action, so this holds whichever order the attributes are declared in.
- `SubmissionLimiter` gains `ensureAllowed()` and `count()`; `hit()` still does both.

## [v1.1.0](https://github.com/marshmallow-packages/bot-shield/compare/v1.0.1...v1.1.0) - 2026-09-29

### Added

- Exception rules accept `always => true`: the match is suppressed without asking the detector. The shipped rules for `CannotUpdateLockedPropertyException` and `CorruptComponentPayloadException` use it, because headless browsers send a stock browser user agent and passed the `user-agent` detector as real visitors.

### Fixed

- Exception rules now also match an exception wrapped by another one (`getPrevious()`), up to ten levels, so an error rethrown by Blade as a `ViewException` is recognised.
- CI: the captcha view names are typed as `view-string` and the package provider is registered for static analysis, so newer Larastan versions pass.

### Upgrading

Sites that published `config/bot-shield.php` keep their old rule list. Add `'always' => true` to the `CannotUpdateLockedPropertyException` and `CorruptComponentPayloadException` rules there, or re-publish the config.

## [v1.0.1](https://github.com/marshmallow-packages/bot-shield/compare/v1.0.0...v1.0.1) - 2026-07-30

### Fixed

- `.claudeignore` and `testbench.yaml` no longer ship in the distributed package. Both are development files for this repository and mean nothing to a consuming application.

No code changes: v1.0.0 and v1.0.1 behave identically.

## [v1.0.0](https://github.com/marshmallow-packages/bot-shield/releases/tag/v1.0.0) - 2026-07-30

First stable release.

Bot traffic hardening and spam protection for Laravel and Livewire, in one config file. Every feature is independently toggleable, and features that need keys disable themselves when the keys are missing, so a site that only wants the exception hardening installs nothing else.

### Added

- Bot-gated Livewire exception hardening, wired through `BotShield::handles()` or the `HardensAgainstBots` trait. Matched exceptions are not reported when the request came from a bot, and always render as a client error rather than a 500.
- Agent rules: one ordered list of user agent patterns evaluated before any detector, with `allow`, `ignore`, `challenge`, `block` and `deny` actions. Search engines default to `block` rather than `deny`, so they keep crawling pages while never submitting a form.
- Bot detector drivers: `user-agent`, `crawler-detect`, or any class implementing `BotDetector`.
- reCAPTCHA v2 and v3 behind a `CaptchaDriver` interface, exposed as the `#[ValidatesRecaptcha]` Livewire attribute, a `Recaptcha` validation rule, a blade component and the `@botShieldRecaptcha` directive. `captcha.hide_badge` hides Google's badge and renders the required notice in its place; `captcha.badge` moves it instead. `captcha.report_outages` reports an unreachable provider to your error tracker, off by default.
- Honeypot form fields wrapping `spatie/laravel-honeypot`, which stays a suggested rather than a required dependency.
- Probe path firewall middleware, submission rate limiting via `#[RateLimitsSubmissions]`, and optional crawler page view refusal.
- Monitoring: a `bot_shield_events` table recording every captcha score, honeypot trip, blocked submission, suppressed exception and probe path hit, with `bot-shield:stats` and `bot-shield:scores` to tune thresholds against real traffic.
- Commands `bot-shield:install`, `bot-shield:doctor`, `bot-shield:stats`, `bot-shield:scores` and `bot-shield:robots`.
- `BotShield::fake()` for testing consuming applications, with scripted captcha answers and in-memory events.
- English and Dutch translations.

### Requirements

PHP 8.3+, Laravel 12 or 13, and Livewire 3 or 4 for the Livewire attributes only. Verified across all twelve combinations on both `prefer-lowest` and `prefer-stable`.
