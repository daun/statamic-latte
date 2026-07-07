# Sources and Verification Log

Provenance for the latte-templates skill. Not loaded at runtime.

## Authoritative sources

| Source | Used for |
|--------|----------|
| latte.nette.org/en/syntax | delimiters, n:attributes, bare strings, whitespace |
| latte.nette.org/en/tags | full tag reference, `$iterator`, loop control, deferred conditions |
| latte.nette.org/en/filters | filter/function signatures, nullsafe `?|`, aliases |
| latte.nette.org/en/template-inheritance | blocks, layout, include, import, embed, scoping |
| latte.nette.org/en/safety-first | escaping model, URL sanitization, attribute semantics |
| latte.nette.org/en/develop | Engine API, cache, Feature flags, loaders, linter |
| latte.nette.org/en/extending-latte | filters/functions/extensions from PHP |
| latte.nette.org/en/sandbox | SecurityPolicy, always-forbidden constructs, `__toString()` gap |
| github.com/nette/latte/releases | version attribution for 3.0.24–3.1.4 features |
| github.com/nette/latte compare v3.1.4...master | identifying unreleased features |

## Verification log

### 2026-07-07 — full fact-check against upstream (audit rework)

Latest stable at verification time: **Latte v3.1.4** (Apr 2026); 3.0.x line continues in parallel (v3.0.26).

Confirmed as documented: `setCacheDirectory()` (3.1.2/3.0.26), smart attribute handling and `class={[...]}` since 3.1.0, nullsafe `?|` since 3.1.0, PHP 8.2–8.5 for 3.1 (8.0+ for 3.0.x), `|>` pipe operator since 3.0.24 (needs PHP 8.5), all five `Latte\Feature` flags with StrictTypes default-on since 3.1, `{skipIf}`/`{exitIf}`/`{iterateWhile}`/`{ifchanged}`/deferred `{/if cond}`, `|commas`/`|column`/`|limit` since 3.1.3, URL-checked attributes list, `{default}` definedness semantics, sandbox safe-policy and `__toString()` gap.

Corrected in this pass:
- `|json` attribute modifier — was labelled "3.1-dev"; actually **unreleased** (master commit Jul 2026, post-3.1.4). Relabelled in filters.md and escaping.md.
- `{embed}` implicit `{block default}` — same: unreleased post-3.1.4. Relabelled in inheritance.md.
- Linter — the `Latte\Linting` namespace, SymbolCheck/TemplateReferenceCheck first-class checks are **master-only**; released docs still use `Latte\Tools\Linter`. php-api.md rewritten to describe released behavior with the rewrite noted as unreleased.
- Added version markers for 3.1-line features (nullsafe pipe, smart attributes, `n:elseif`, Feature enum, 3.1.3 filters) so 3.0.x users are not misled.

## Decisions

- Skill scope pinned to upstream Latte 3.x only; the Statamic addon layer is documented by sibling skills in this repo (`docs/skills/statamic-latte-templates`, `.claude/skills/*`). The description carries an explicit anti-trigger for those.
- Unreleased features stay in the skill (they explain "why doesn't X work" questions from users reading master docs) but must carry an "unreleased — master post-X.Y.Z" label.

## Gaps

- `--strict`/`--debug` flag semantics of released `latte-lint` not documented in detail (only their existence verified).
- `{dedent}` tag mentioned in v3.1.4 release notes but not covered; only `Feature::Dedent` is documented here.
- `Engine::setSyntax()` (3.0.24) not covered.

## Changelog

- 2026-07-07: Audit against skill-writer principles. Renamed skill `writing-latte-templates` → `latte-templates` (match directory), moved six reference files under `references/`, converted routing to an "open when" table, rewrote description in third person with anti-triggers, added SPEC.md and this file, applied upstream fact-check corrections above.
