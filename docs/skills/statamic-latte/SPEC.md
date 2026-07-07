# statamic-latte — Maintenance Contract

Not loaded at runtime. Read this before changing the skill.

## Intent

Make Claude an expert user of the `daun/statamic-latte` addon: writing, reviewing, and debugging `.latte` templates in Statamic sites — Statamic tags from Latte, modifiers as filters, the Content/Deferred data layer, view/layout resolution, cross-engine composition, and caching. The skill documents **this repository's current main branch**; the addon source and tests are the ground truth.

## Execution shape

Reference-backed expert. `SKILL.md` carries the always-needed core (one-minute overviews of views, data, tags, modifiers; top gotchas) and routes to four flat reference files under `references/` via an "Open when you need to..." table. No scripts, no assets, no fork context.

## Non-negotiable constraints

- **Deltas only**: document what the addon adds or changes on top of plain Latte. Upstream Latte behavior (tag syntax, filters, inheritance, escaping) belongs to the `latte-templates` sibling skill — link, don't duplicate.
- **Source of truth is the repo**: every behavioral claim must be verifiable against `src/` and `tests/` of this repository; log verifications and corrections in `SOURCES.md`.
- `references/` stays flat; every file must have a routing row in `SKILL.md`.
- No version markers for the addon itself — the skill tracks main; note Statamic/PHP platform requirements only where they change behavior.
- Keep the symptom → cause table in `references/data.md` current; it is the primary debugging entry point.
- **Description framing**: the description presents the skill as authoring guidance ("Authoring Latte templates in Statamic sites..."), never as a "reference for the addon", and must not mention other skills by name. Scope boundaries toward sibling skills live in the SKILL.md body, not the description.

## File responsibilities

| File | Owns |
|---|---|
| `SKILL.md` | Addon overview, one-minute summaries (views/layouts, data shapes, three tag spellings, modifiers), top gotchas, routing table |
| `references/statamic-tags.md` | The `s:` bridge: spellings, argument syntax, body scope/result dispatch, `as:`/`content:`, pagination, forms, blocked tags, per-tag notes, reserved-syntax warning |
| `references/data.md` | Content/Deferred wrappers, access rules, resolve helpers, attribute behavior, symptom → cause table |
| `references/views-and-composition.md` | View name resolution, automatic layouts, `{section}`/`{yield}`, `{slot}`, `{antlers}` interop |
| `references/caching.md` | `{cache}` fragment caching, `{nocache}` static-cache holes, compiled-template cache |

## Out of scope

- Plain Latte syntax and semantics — `latte-templates` sibling skill.
- Writing Latte extensions, custom tags, or compiler passes — `latte-extensions` sibling skill.
- Antlers or Blade templating in their own right (only their interop with Latte).
- Addon installation/contribution workflows (README territory).

## Update triggers

- Behavior changes in `src/` (tag bridge, data layer, view resolution, caching): update the affected reference file and log in `SOURCES.md`.
- New blocked tags, params, or per-tag incompatibilities: update `references/statamic-tags.md` tables.
- User-reported wrong or missing fact: fix, then record the correction in the `SOURCES.md` verification log.
