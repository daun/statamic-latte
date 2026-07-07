# Implementation Report: Data-layer rebase (Plan B)

Implements `docs/plan-data-layer-rebase.md` steps 1–3. Consolidates the addon's
three data classes into one template-facing class plus boundary statics, and
defers eager relationship augmentation at the render boundary.

## Status

| Step | Description | State |
|---|---|---|
| 1 | Delegate `Resolver::actual` to Statamic's Blade `value()` helper | ✅ shipped |
| 2 | Fold `Normalizer` into `Content` as `wrap`/`wrapAll`/`unwrap` statics | ✅ shipped |
| 3 | Defer augmentation of non-empty relationship fields | ✅ shipped |
| 4 | Docs/changelog/cleanup | ⏳ not in this pass (report only) |

All three code steps committed. Quality gates green after every step:

```
composer test      → 378 passed (553 assertions)   [was 360 at baseline]
composer analyse   → PHPStan level 5, no errors
composer lint      → Pint, passed
```

## Commits

```
a054946 refactor: delegate Resolver::actual to Statamic's Blade value() helper
3a61ff3 refactor: fold Normalizer into Content as wrap/wrapAll/unwrap statics
770ba34 refactor: address review feedback for data-layer steps 1-2
c6dcaf3 perf: defer augmentation of non-empty relationship fields at render boundary
```

Diffstat (`c887ac7..HEAD`): 14 files, +523 / −132.

---

## Step 1 — Resolver delegation

`Resolver::actual()`'s hand-rolled unwrap chain (`Value`, `LabeledValue`,
`ArrayableString`, `Values`, `FluentTag`, `Modify`) was replaced by a
loop-until-stable delegation to Statamic core's autoloaded
`Statamic\View\Blade\value()` helper, keeping the query-builder resolution
Statamic's helper omits. Future Statamic wrapper types are now handled upstream
for free.

- Public API unchanged (`actual()`, `drill()`, `get()`, `resolve_value()`, the
  `resolve`/`r` functions and `|resolve` filter).
- The loop peels wrappers exposed by a previous unwrap (e.g. a `Value` whose
  augmented value is an `ArrayableString`); an object-guard bounds it.

**Intentional behavior change:** a bare non-string-backed `ArrayableString` now
resolves via `->value()` instead of a string cast (more correct; string-backed
fields are identical). Documented for a future CHANGELOG.

**Tests:** `ResolverTest` passes unchanged; added coverage for the
loop-until-stable case, `Modify`, and `FluentTag`.

## Step 2 — Normalizer → Content

The four `Normalizer` statics moved onto `Content`:

| Old | New |
|---|---|
| `Normalizer::normalize` | `Content::wrap` |
| `Normalizer::data` | `Content::wrapAll` |
| `Normalizer::normalizeArray` | protected `Content::wrapArray` |
| `Normalizer::unwrap` | static `Content::unwrap` |

The `Content->unwrap()` **instance** escape hatch was renamed to `source()` to
avoid the static/instance name collision. All call sites updated, including the
two compiler extensions (`AttributeNormalizationExtension`, `AntlersNode`) that
bake the class FQCN into **compiled template PHP**.

`Normalizer` is retained as a **deprecated shim** delegating to `Content`
(removal pinned to 3.0). This guards existing sites: Latte's compile cache does
not invalidate on addon PHP changes, so already-compiled templates still
reference `Normalizer::unwrap` until recompiled.

**Tests:** `NormalizerPrototypeTest` → `ContentWrapTest`, rewritten to the new
API, plus a shim-delegation test proving `Normalizer::normalize/data/unwrap`
still work. `grep "Normalizer::" src/` returns nothing (shim delegates to
`Content`).

## Step 3 — Deferred relationship augmentation (the perf fix)

Statamic's cascade delivers every blueprint field as a deferred `Value`. The
eager top-level normalization called `->value()` on each, and for relationship
fieldtypes that ran the field's query builder + augmentation — for every
relationship field, used by the template or not.

Fix: a new `Deferred` proxy (`src/Data/Deferred.php`), created **only** in
`Content::wrapAll()` and **only** for non-empty relationship `Value`s. The
query + augmentation runs when the template first touches the variable.

Design invariants (all correctness-critical):

- **Emptiness from raw IDs.** `Value::isRelationship()` / `raw()` call
  `resolve()`, which unwraps the deferred closure to raw stored IDs but does
  **not** augment or run the query (verified in `vendor/.../Fields/Value.php`).
- **Only non-empty relationships are deferred.** A `Deferred` is an
  always-truthy object; empty relationships stay eager and correctly falsy
  (`[]`/`null`), so `{if $related}` is unchanged.
- **Non-relationship Values are never deferred** (markdown, bard, …) — eager
  evaluation is what gives Latte correct scalar/truthiness semantics.
- **Never nested.** Nested access is already lazy via `Content`; `Deferred`
  exists only at the top level.

`Deferred` implements `ArrayAccess`, `Countable`, `IteratorAggregate`,
`JsonSerializable`, `__toString`, handling both materialized shapes (plain array
for a list, `Content` for a `max_items: 1` single item). It is peeled at every
boundary: `Content::unwrap` (materialize-then-unwrap for modifiers / n:attr /
Antlers) and `Resolver::actual` (pre-peel to the source `Value`).

**Tests:** new `DeferredTest` (12 tests) covering the deferral predicate,
truthiness matrix (empty→else, non-empty→truthy), foreach, single-entry
property access, `|length` via the modifier boundary, `|resolve`, Antlers
crossing, JSON output, and reflection proof that the proxy stays unresolved
until touched. Fixtures gained a `related_pages` list-relationship field.

### Two correctness fixes surfaced during review

1. **`count()` divergence.** The initial raw-ID count fast-path could report
   more items than `{foreach}` yields when a referenced entry is unpublished or
   deleted (augmentation drops it). Removed the fast-path — `count()` now always
   materializes, so it equals iteration length by construction. (Plan said
   "correctness first".)
2. **`jsonSerialize`.** Returning the materialized `Content` produced empty
   `{}`/`[{},{}]` JSON (a `JsonSerializable` contract violation). Now returns
   `Content::unwrap($this->materialize())` so `json_encode` emits real entry
   data. Covered by a new test.

**Known parity note:** `json_encode($deferred)` now emits augmented Statamic
entry data. The pre-refactor eager array-of-`Content` encoded to `[{},{}]`, so
this is a strict improvement rather than byte-for-byte parity — worth a
CHANGELOG line.

---

## Review process

Each phase was reviewed by an independent GPT-5.5 subagent; iterated until both
sides agreed.

- **Steps 1+2:** first pass 8/10, no blockers. Applied the actionable
  improvements (FluentTag/Modify tests, loop-guard comment, pinned shim
  version). Confirmation pass: **SATISFIED, 9/10**.
- **Step 3:** first pass 7/10, SHIP-conditional — flagged the `jsonSerialize`
  contract violation (blocker) and the `count()` raw-ID divergence (which was
  reproducible with the original fixture). Both fixed. Confirmation pass:
  **SATISFIED, 9/10, no blockers**.

## Not done (out of scope for this pass)

- **Step 4** — README internals + CHANGELOG entries. Three intentional
  behavior notes should land there: Resolver `ArrayableString` edge case,
  `Normalizer` deprecation / `Content->source()` rename, and the deferred
  relationship `json_encode` note.
- No upstream statamic/cms PR, no full top-level laziness (incompatible with
  correct truthiness for plain PHP locals), no `Resolver`↔`Content` merge (they
  are opposite codecs, deliberately separate).
