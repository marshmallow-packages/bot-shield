---
name: bot-shield-overview
description: Orient in the marshmallow/bot-shield repo - what it is, how the pieces fit, and the invariants. Invoke first when starting work here.
---

# bot-shield overview

`marshmallow/bot-shield` is a standalone, public Laravel package (Packagist, MIT) for bot traffic hardening and spam protection in Laravel + Livewire apps: exception noise suppression, honeypot, reCAPTCHA, crawler rules and monitoring. It is **not** an application: there is no Laravel app, no deploy target, no Forge site, no `.env`, and no frontend build. Runtime code lives in `src/`; a Testbench workbench app under `workbench/` stands in for a host application during development.

## How the pieces fit

- **`src/`** - the package. `BotShieldServiceProvider` is the single wiring point (config merge, publish tags, migrations, views, translations, commands, routes, middleware). `BotShield` is the entry class behind the `BotShield` facade.
- **Publishable resources** - `config/bot-shield.php`, `database/migrations/`, `resources/views/`, `lang/`. Each has its own `bot-shield-*` publish tag plus the umbrella `bot-shield` tag; the README documents every tag and must stay in sync.
- **`tests/`** - Pest 4 on Orchestra Testbench. `TestCase` boots the package provider; `ArchTest` enforces architectural rules; type coverage is gated at 100%.
- **`workbench/`** - the throwaway host app (models, providers, routes, factories, seeders) used by `composer build` / `composer serve`. Never ship behavior that only works because of workbench wiring.
- **`resources/boost/skills/bot-shield-development/SKILL.md`** - the Boost skill shipped to consumers. Regenerate it from the implementation with the `package-generate-skill` skill whenever public APIs, config, commands, tags, or README promises change.
- **`.agents/`** - agent config, with `.claude` and `CLAUDE.md` symlinked to `.agents` and `AGENTS.md`. Local skills: `package-scaffold`, `package-testing`, `package-release`, `package-compatibility`, `package-generate-skill`.
- **CI** - `.github/workflows/tests.yml` runs the matrix; `update-changelog.yml` maintains `CHANGELOG.md`.

## Invariants (do not violate)

- **This repo is public.** Never commit customer names, hostnames, credentials, internal URLs, Linear/Sentry identifiers, or internal playbooks. `docs/BRIEF.md` and `docs/AI-COST-OPTIMIZATION-PORTABLE.md` are deliberately gitignored - keep them that way.
- **Support matrix**: PHP `^8.3`, `illuminate/support` `^12.0||^13.0`, Testbench `^10.0||^11.0`. Any code, dependency, or CI change must hold across the whole matrix - use the `package-compatibility` skill.
- **`composer test` is the gate**: `analyse` (Larastan) + `lint:check` (Pint) + `test:types` (100% type coverage) + `test:unit`. All four must pass before anything is considered done.
- **Provider-first wiring**: add capabilities through `BotShieldServiceProvider` using Laravel-native package APIs. No new abstraction layers unless the extension point is real.
- **Naming stays aligned** across composer name, namespace `Marshmallow\BotShield\`, config key `bot-shield`, publish tags `bot-shield-*`, and docs.
- **Test observable behavior** through public APIs, provider wiring, commands, routes, published resources, and documented promises - not internals.
- **No em-dashes** in any generated text: code, docs, commits, PR bodies.
- **Never release autonomously** - tags, releases, and changelog publishing are explicitly requested only (`package-release`).

## Gotchas (stable)

- `composer.lock` is gitignored on purpose (library, not app) - do not commit it or rely on locked versions.
- `post-autoload-dump` runs `clear` (`testbench package:purge-skeleton`) then `prepare` (`package:discover`); purge-skeleton deletes generated workbench skeleton files, so do not hand-edit anything it regenerates.
- `.claude` -> `.agents` and `CLAUDE.md` -> `AGENTS.md` are symlinks. Write to the real paths (`.agents/`, `AGENTS.md`); editing through the symlink can surprise git.
- The placeholder migration, view, command, and example tests are scaffold artifacts. Replace them as real features land rather than accumulating around them.
- Type coverage is `--min=100`, so every new symbol needs full type annotations or CI fails.

For live structure use `fd`/`rg`/`ast-grep`, not a hardcoded file tree.
