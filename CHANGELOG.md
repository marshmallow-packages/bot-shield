# Release Notes

## [Unreleased](https://github.com/marshmallow-packages/bot-shield/compare/v0.1.0...1.x)

### Added

- Bot-gated Livewire exception hardening, wired through `BotShield::handles()` or the `HardensAgainstBots` trait. Matched exceptions are not reported when the request came from a bot, and always render as a client error rather than a 500.
- Agent rules: one ordered list of user agent patterns evaluated before any detector, with `allow`, `ignore`, `challenge`, `block` and `deny` actions.
- Bot detector drivers: `user-agent`, `crawler-detect`, or any class implementing `BotDetector`.
- reCAPTCHA v2 and v3 behind a `CaptchaDriver` interface, exposed as the `#[ValidatesRecaptcha]` Livewire attribute, a `Recaptcha` validation rule, a blade component and the `@botShieldRecaptcha` directive.
- Honeypot form fields wrapping `spatie/laravel-honeypot`, which stays a suggested rather than a required dependency.
- Probe path firewall middleware, submission rate limiting via `#[RateLimitsSubmissions]`, and optional crawler page view refusal.
- Monitoring: a `bot_shield_events` table recording every captcha score, honeypot trip, blocked submission, suppressed exception and probe path hit, with `bot-shield:stats` and `bot-shield:scores`.
- Commands `bot-shield:install`, `bot-shield:doctor`, `bot-shield:stats`, `bot-shield:scores` and `bot-shield:robots`.
- `BotShield::fake()` for testing consuming applications, with scripted captcha answers and in-memory events.
- English and Dutch translations.

## [v0.1.0](https://github.com/marshmallow-packages/bot-shield/compare/...v0.1.0) - 202x-xx-xx

Initial pre-release.
