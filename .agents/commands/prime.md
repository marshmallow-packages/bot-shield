---
description: Prime context for bot-shield - orient fast without reading everything
---

Orient yourself using cheap, targeted commands. Do NOT bulk-read files.

1. Invoke the `bot-shield-overview` skill for stable architecture + invariants.
2. Read `AGENTS.md` (project rules; `CLAUDE.md` symlinks to it) if not already in context.
3. Run `git status --short` and `git log --oneline -10`.
4. Read `docs/BRIEF.md` only when the task concerns the feature scope or roadmap (it is large and gitignored).
5. Only then read the files the task needs (locate with `rg`/`fd`/`ast-grep`, not blind reads).

Report a 3-5 line summary and ask a clarifying question if the task is ambiguous (95% confidence rule).
