# latte-templates — Sources and Verification Log

Not loaded at runtime. Provenance for factual claims and a log of verifications/corrections.

## Authoritative sources

| Source | Covers | Authority |
|---|---|---|
| https://latte.nette.org/en/tags | All tags and n:attributes | Official docs |
| https://latte.nette.org/en/filters | Filters and functions | Official docs |
| https://latte.nette.org/en/syntax | Syntax, bare strings, n:attributes | Official docs |
| https://latte.nette.org/en/safety-first | Context-aware escaping, sandbox | Official docs |
| https://latte.nette.org/en/template-inheritance | Blocks, layout, embed, visibility | Official docs |
| https://latte.nette.org/en/develop | Engine API, parameters, extensions | Official docs |
| https://github.com/nette/latte/releases | Release timeline and changelogs | Upstream repo |
| https://github.com/nette/latte (master source, `compare/v3.1.4...master`) | Master-only behavior | Upstream repo |

## Verification log

### 2026-07-07 — full fact-check (16 claims, background research agent)

Confirmed against docs + repo: nullsafe filter pipe `?|`, smart attribute type handling (null/false omit, arrays for class/style/data-*), `n:elseif` sibling chains, StrictTypes default ON, PHP 8.2–8.5 requirement, `|>` pipe operator needing PHP 8.5, deferred closing conditions `{/if expr}`, `{skipIf}`-all → `{else}` behavior.

Corrections applied:
- Latest stable is **3.1.4** (April 2026); the skill previously labeled shipped 3.1 features as "3.1-dev".
- `setCacheDirectory()` is the current name; `setTempDirectory()` is the older alias. Examples now use `setCacheDirectory()`.
- `|json` attribute modifier, `{embed}` implicit `{block default}` for loose content, and the `Latte\Linting` linter rewrite (SymbolCheck, TemplateReferenceCheck; `Latte\Tools\Linter` kept as BC alias) are **master-only, post-3.1.4** at verification time.

### 2026-07-07 — rewrite for current master (user direction)

Removed all per-feature version markers and migration-only content per the policy now recorded in `SPEC.md`.

## Decisions

- **Document master, no version markers.** The skill documents current Latte master with no per-feature version markers; the covered version (3.1) is stated once in the `SKILL.md` overview and nowhere else. Users are assumed to run the latest Latte. Consequence: `|json`, `{embed}` implicit default block, and the `Latte\Linting` first-class linter checks are documented as available even though at verification time they were master-only (post-3.1.4). A user pinned to exactly 3.1.4 would find those three not working yet.
- **Migration content omitted.** The `accept` filter (3.0→3.1 no-op) and `Feature::MigrationWarnings` were deleted as noise under the assume-latest policy.
- **PHP version mentions retained.** "PHP 8.4 parens-free dereference", "PHP 8.5 pipe operator" are runtime PHP requirements, not Latte version noise.

## Known gaps

- `{dedent}` tag semantics beyond the `Feature::Dedent` flag not covered in depth.
- `Engine::setSyntax()` / double-brace syntax mode not documented.
- `latte-lint` CLI flag semantics (e.g. `--strict`) not exhaustively verified.

## Changelog

- 2026-07-07 — Audit against skill-writer: renamed to `latte-templates`, moved refs under `references/`, routing table, third-person description with anti-trigger, fact-check corrections applied.
- 2026-07-07 — Rewrite for current master: single version mention in overview, all other version markers and migration content removed; SPEC/SOURCES record the policy.
