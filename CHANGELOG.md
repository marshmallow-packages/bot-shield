# Release Notes

## [Unreleased](https://github.com/marshmallow-packages/bot-shield/compare/v1.0.1...HEAD)

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
