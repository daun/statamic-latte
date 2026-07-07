# statamic-latte — Sources and Verification Log

Not loaded at runtime. Provenance for factual claims and a log of verifications/corrections.

## Authoritative sources

| Source | Covers | Authority |
|---|---|---|
| `src/` (this repository) | All addon behavior — tag bridge, data layer, view/layout resolution, caching | Primary — the addon itself |
| `tests/` (this repository) | Behavioral contracts (Feature/TagTest, Tags/*, Unit/TagExpressionSyntaxTest, ContentWrapTest, SlotTest...) | Primary — executable spec |
| `composer.json` | Platform requirements (PHP ^8.3, statamic/cms ^6.0, miko/laravel-latte ^3.0) | Primary |
| `vendor/miko/laravel-latte` | Laravel-flavored tags shipped alongside ({csrf}, {asset}, {x}...) | Upstream dependency |
| https://statamic.dev/tags, https://statamic.dev/static-caching | Statamic-side semantics the bridge maps onto | Official Statamic docs |
| https://latte.nette.org | Upstream Latte behavior the skill assumes (owned by the `latte-templates` skill) | Official Latte docs |

## Verification log

### 2026-07-07 — full fact-check (45 claims, two background research agents over src/ + tests/)

Confirmed: the three `s:` spellings; single-tag compile restriction; `$value` body scope with real Latte foreach (`$iterator`/`{sep}` support); falsy-result body gating; result stringification rules; last-colon argument parsing; bare-filter-pipe compile error; `as:`/`content:`/`__sl_tag` consumption (dynamic `as:` forwarded); raw-result capture semantics; the 13-tag blocklist (cache, foreach, loop, partial, switch, translate, trans, trans_choice, yield, section, scope, increment, dump); compile-time vs runtime binding; `(s:` rewriter protection rules; all form-tag behavior; per-tag notes (glide, svg, vite, search, collection, cookie/session, 404/redirect); pagination; modifier bridging (Latte-filter-wins collisions, empty context, unwrapped values); Content/Deferred normalization (wrapAll at render boundary, no `__call`, LogicException on array writes, full-augmentation iteration, Deferred only for top-level non-empty relationships, published-set counts); resolve()/r() coalesce vs |resolve drill; n:attr Content spreading; max_items:1 → single Content; Laravel view-finder resolution with relative paths and namespaces; automatic layout via current_layout with include/embed exemption; section/yield backed by Statamic's cascade (cross-engine); {slot} ≡ BlockNode::create; {antlers} argument/n:attribute rejection and variable unwrapping; all {cache} params, key hashing, GET-only + config gate, ['site','auth'] default scope, falsy-output permanent miss, params-in-key; {nocache} via Statamic's BladeDirective with view extraction; config defaults (compiled → view.compiled, auto_refresh → app.debug, strict_parsing/scoped_loop_variables false); miko/laravel-latte tag list.

Corrections applied:
- Pair closers do NOT require the full method name: `{/s:collection}` also closes `{s:collection:pages}` (TagMethodSyntax normalizes closers to the base name). Fixed in `references/statamic-tags.md`.

Disputed and re-verified in the skill's favor:
- The `{ifset}` shape-dependence claim: a verifier argued `[]` fails `isset()` — wrong; `php -r` confirms `isset($x = [])` is true and `isset(null)` is false, so the documented behavior (empty multi-item → `[]` is set, empty max_items:1 → `null` is not) stands.
- `.latte` winning bare-name conflicts: `ServiceProvider` calls `Factory::addExtension('latte', ...)`, which prepends the extension, so `.latte` is checked first — confirmed via Laravel semantics.

Verified against upstream Latte (not this repo): dot-less quoted `{include 'welcome'}` parsing as a block name (Latte's file/block disambiguation rule; the `file` keyword advice stands).

## Decisions

- **Deltas-only scope.** The skill documents what the addon adds/changes on top of plain Latte; upstream behavior is delegated to the `latte-templates` sibling. Cross-references updated after that skill was renamed from `writing-latte-templates`.
- **No addon version markers.** The skill tracks this repository's main branch; the code and tests are the ground truth, so per-feature versioning would only go stale.
- **Repo-internal provenance.** Unlike `latte-templates` (verified against external docs), claims here cite `src/`/`tests/` — behavior changes land in the same commits that would invalidate the skill, so keep the skill in sync with data-layer or bridge refactors (see `SPEC.md` update triggers).

## Known gaps

- `{yield}`-inside-`{cache}` and `{nocache}`-inside-`{cache}` breakage is documented from placeholder mechanics but has no covering test in `tests/` — flagged unverifiable by the sweep; worth a regression test upstream.
- miko/laravel-latte's own tag semantics ({x} components, Livewire tags) are only enumerated, not documented in depth — intentionally, as they're that package's territory.

## Changelog

- 2026-07-07 — Audit against skill-writer: renamed frontmatter to `statamic-latte` (was `writing-statamic-latte-templates`), moved the four reference files under `references/`, converted routing to an "open when" table, fixed the stale `writing-latte-templates` cross-reference, added SPEC.md/SOURCES.md. Fact-checked 45 claims against src/ + tests/ (one correction: base-name pair closers).
- 2026-07-07 — Description reframed per user direction: authoring guidance ("Authoring Latte templates in Statamic sites..."), not a "reference for the addon"; sibling-skill mentions removed from the description (policy recorded in SPEC.md).
