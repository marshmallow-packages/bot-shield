# Release Notes

## [Unreleased](https://github.com/marshmallow-packages/bot-shield/compare/v0.1.0...1.x)

### Added

- Bot-gated Livewire exception hardening, wired through `BotShield::handles()` or the `HardensAgainstBots` trait. Matched exceptions are not reported when the request came from a bot, and always render as a client error rather than a 500.
- Agent rules: one ordered list of user agent patterns evaluated before any detector, with `allow`, `ignore`, `challenge`, `block` and `deny` actions.
- Bot detector drivers: `user-agent`, `crawler-detect`, or any class implementing `BotDetector`.
- reCAPTCHA v2 and v3 behind a `CaptchaDriver` interface, exposed as the `#[ValidatesRecaptcha]` Livewire attribute, a `Recaptcha` validation rule, a blade component and the `@botShieldRecaptcha` directive.
- `captcha.hide_badge` hides the reCAPTCHA badge and renders Google's required notice in its place, per site or per tag. `captcha.badge` moves it instead, on the invisible widget.
- Honeypot form fields wrapping `spatie/laravel-honeypot`, which stays a suggested rather than a required dependency.
- Probe path firewall middleware, submission rate limiting via `#[RateLimitsSubmissions]`, and optional crawler page view refusal.
- Monitoring: a `bot_shield_events` table recording every captcha score, honeypot trip, blocked submission, suppressed exception and probe path hit, with `bot-shield:stats` and `bot-shield:scores`.
- Commands `bot-shield:install`, `bot-shield:doctor`, `bot-shield:stats`, `bot-shield:scores` and `bot-shield:robots`.
- `BotShield::fake()` for testing consuming applications, with scripted captcha answers and in-memory events.
- `captcha.report_outages` reports an unreachable provider to the error tracker, off by default.
- English and Dutch translations.

### Fixed

- `bot-shield:install` now recognises a `withExceptions()` closure written with a return type, a static closure, a renamed parameter or a fully qualified `Exceptions` class. It previously matched one exact spelling and printed manual instructions for every other one.
- `BotShield::fake()` answers as the driver the application configured, so a v3 site no longer renders v2 markup under the fake. Pass a driver name to override it.
- `bot-shield:doctor` reports whether `bot-shield::messages` resolves, so an application whose translation loader cannot read package lang files finds out before its visitors read raw keys off a form.
- `bot-shield:doctor` detects a scheduled `model:prune` instead of always warning that pruning is unscheduled.
- The captcha `missing` and `failed` messages no longer describe a checkbox the invisible driver never shows, and no longer differ from each other, which told a caller which check it failed.

## [v0.1.0](https://github.com/marshmallow-packages/bot-shield/compare/...v0.1.0) - 202x-xx-xx

Initial pre-release.
