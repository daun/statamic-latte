# Latte Templates Skill Specification

## Intent

Give an agent working knowledge of the upstream Latte 3.x templating engine — syntax, escaping model, composition, expression language, and PHP API — so it can write, review, and debug `.latte` templates without re-reading latte.nette.org each time.

## Scope

In scope:
- Latte 3.x tag and n:attribute syntax, filters, functions, expression language
- Context-aware escaping, attribute semantics, URL sanitization, whitespace rules
- Blocks, layout inheritance, includes, imports, embeds, variable scoping
- `Latte\Engine` setup, custom filters/functions/extensions, sandbox, linter

Out of scope:
- Anything layered on top of Latte by a CMS or framework (Statamic addon behavior, Nette Application tags like `n:href`/`{control}`, Symfony bridges) — this repo's addon layer is covered by sibling skills
- Writing custom compiler nodes/tags in depth (only an overview; upstream docs at latte.nette.org/en/custom-tags)
- Latte 2.x

## Users And Trigger Context

- Primary users: developers authoring or debugging `.latte` templates, or embedding Latte in PHP.
- Common user requests: "why doesn't this {foreach} work", "how do I escape X in Latte", "add a custom filter", "block not overriding in layout", "what does n:ifcontent do".
- Should not trigger for: Twig/Blade/other engines; Statamic `s:` tags or addon data-layer questions; Nette framework (non-templating) questions.

## Runtime Contract

- Required first actions: none — `SKILL.md` is self-sufficient for common template edits.
- Required outputs: correct `.latte` syntax; never manually escaped output; no `|noescape` on untrusted data.
- Non-negotiable constraints: keep guidance aligned with Latte 3.x stable; mark unreleased features with their version explicitly.
- Expected bundled files loaded at runtime: one `references/*.md` per lookup need, routed from `SKILL.md`.

## Source And Evidence Model

Authoritative sources:
- latte.nette.org documentation (tags, filters, syntax, template-inheritance, safety-first, develop, extending-latte, sandbox, cookbook)
- github.com/nette/latte (releases, source) for version verification

See `SOURCES.md` for the verification log. Do not store secrets or private URLs.

## Reference Architecture

- `SKILL.md` contains: universal syntax, escaping rules, essential tags, expression sugar, top gotchas, minimal PHP setup, routing table.
- `references/` contains: `tags.md`, `filters.md`, `inheritance.md`, `expressions.md`, `escaping.md`, `php-api.md` — one lookup domain each.

## Validation

- Lightweight validation: skill-format check (frontmatter, name/directory match, all routed files exist).
- Deeper validation: spot-check version-sensitive claims against latte.nette.org and nette/latte releases when Latte publishes a new minor version.
- Acceptance gates: every `references/` file has a routing entry in `SKILL.md`; no dead relative links.

## Known Limitations

- Facts are a snapshot of Latte 3.x as of the last verification date in `SOURCES.md`; unreleased features are labelled with the version they target and may change.
- Filter/function tables abbreviate rare parameters; consult latte.nette.org for exhaustive signatures.

## Maintenance Notes

- When to update `SKILL.md`: syntax-level changes in a new Latte minor version; a recurring template bug not covered by the gotchas list.
- When to update `references/*`: new/changed tags, filters, engine features; version-label promotions (e.g. a dev feature ships).
- When to update `SOURCES.md`: every verification pass or material content change.
