# latte-templates — Maintenance Contract

Not loaded at runtime. Read this before changing the skill.

## Intent

Make Claude an expert user of the Latte templating engine (latte.nette.org): writing, reviewing, and debugging `.latte` templates, and integrating/configuring `Latte\Engine` from PHP. The skill tracks **current Latte master** and assumes users run the latest release (3.1 line at last verification).

## Execution shape

Reference-backed expert. `SKILL.md` carries the always-needed core (syntax, escaping model, essential tags, top gotchas, minimal PHP setup) and routes to six flat reference files under `references/` via an "Open when you need to..." table. No scripts, no assets, no fork context.

## Non-negotiable constraints

- **Versioning policy**: track current Latte master; state the covered version once in the `SKILL.md` overview and nowhere else — no per-feature version markers ("3.1+", "unreleased", "since ..."). PHP-language version mentions (e.g. "PHP 8.5 pipe operator") are fine — those are runtime requirements, not Latte versions.
- **Scope**: upstream Latte only. No CMS- or framework-specific layers (Statamic, Nette Application, Symfony/Laravel bridges) — those belong to sibling skills.
- `references/` stays flat; every file must have a routing row in `SKILL.md`.
- Escaping guidance must never suggest manual escaping or weaken the "never `|noescape` untrusted content" rules.
- All factual claims must be verifiable against the sources in `SOURCES.md`; log corrections there.

## File responsibilities

| File | Owns |
|---|---|
| `SKILL.md` | Core syntax, escaping rules that matter everywhere, essential tags, expression sugar summary, top gotchas, minimal PHP setup, routing table |
| `references/tags.md` | Every tag and n:attribute, `$iterator`, loop control |
| `references/filters.md` | Every filter and expression function with signatures |
| `references/inheritance.md` | Blocks, layout, include, import, embed, define, variable visibility |
| `references/expressions.md` | Allowed/forbidden PHP, bare strings, filter syntax edge cases |
| `references/escaping.md` | Per-context escaping, attribute type semantics, URL sanitization, whitespace |
| `references/php-api.md` | Engine setup, extensions, sandbox, linting, debugging |

## Out of scope

- Older Latte versions and version-migration guidance (assume latest; migration shims like the `accept` filter or `Feature::MigrationWarnings` are deliberately omitted).
- Latte 2.x syntax.
- Writing Latte extensions (compiler passes, custom tags) beyond registering them — that is the `latte-extensions` sibling skill.
- Statamic/CMS integration — `statamic-latte-templates` sibling skill.

## Update triggers

- New Latte release or notable master changes: re-verify against `SOURCES.md` sources, update facts, bump the version mentioned in the `SKILL.md` overview when a new minor ships, log in `SOURCES.md`.
- User-reported wrong or missing fact: fix, then record the correction in the `SOURCES.md` verification log.
