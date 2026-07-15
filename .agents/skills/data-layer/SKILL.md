---
name: data-layer
description: Covers src/Data (Content, Deferred, Resolver, deprecated Normalizer) and the render-boundary wrap/unwrap semantics. Use when adding support for a new Statamic value type, changing relationship deferral, debugging truthiness/emptiness/count bugs in templates, touching resolve()/r()/|resolve, or renaming any class whose FQCN appears in compiled templates.
---

# Data layer: Content, Deferred, Resolver

The data layer converts Statamic's data model (deferred `Value` objects, Augmentables, `Values` groups, query builders) into plain-PHP shapes that Latte templates can trust, and converts them back at every exit point. Everything in `src/Data/` exists because of this two-way boundary.

## When to use this skill

- Adding/changing handling of a Statamic value type in `Content::wrap`/`unwrap`
- Changing what `Deferred` does when a template touches it (count, isset, json, echo)
- Debugging: `{if $field}` takes the wrong branch, `|length` disagrees with `{foreach}`, `json_encode` emits `[{},{}]`, a variable prints nothing
- Touching `resolve()`/`r()` functions, the `|resolve` filter, or `resolve_value()`
- Renaming ANY class under `src/Data` or `src/Latte/Support` (see the rename hazard below)

## Why this layer exists

Per `docs/plan-data-layer-rebase.md` §1, Statamic's native objects misbehave in Latte:

1. **Always-truthy Values**: the cascade delivers every blueprint field as a deferred `Statamic\Fields\Value` object. Objects are truthy in PHP, so `{if $subtitle}` would always be true even for an empty field.
2. **No recursion**: `Values` doesn't recurse, and `Value::__get` can hit unresolved query builders on relationship fields.
3. **No augmentation cache**: `Value::value()` re-runs `fieldtype->augment()` on every access. `Content` adds the per-key cache Statamic lacks.
4. **Perf**: eagerly normalizing the whole variable bag would execute every relationship field's query at render start, whether or not the template uses it. `Deferred` fixes that.

Read `docs/plan-data-layer-rebase.md` (design intent, rejected alternatives) and `docs/report-data-layer-rebase.md` (what shipped, the two review-driven fixes) before changing anything here.

## The direction convention (memorize this)

- **Wrap on the way IN**: `Content::wrapAll` runs once, at `NormalizingEngine::get` (src/Latte/NormalizingEngine.php), on the incoming template variable bag. Tag output is wrapped via `Content::wrap` in `Tags::fetchTag` (src/Latte/Support/Tags.php), the shared helper behind `fetch`/`fetchWithContent`; the paginator recovery path bypasses `Content::wrap` (see tag-bridge skill).
- **Unwrap at every hand-off OUT**: `ModifierExtension::applyModifier` (modifiers), `AttributeNormalizationExtension` (n:attr, emits `Content::unwrap` into compiled code), `AntlersNode` ({antlers} blocks, unwraps `get_defined_vars()`), and `Tags::stringifyResult` (self-closing tag echo).
- **Resolver peels DOWN and never wraps**: `Resolver::actual` produces final raw values for template-author use. `Content::wrap` and `Resolver::actual` are opposite codecs — never merge them, never make `Resolver` return a `Content`.

## The Content codec

`Content` (src/Data/Content.php) is both the lazy instance wrapper and the static boundary codec.

### Content::wrap — normalization, order matters, do not reorder

| Input | Output |
|---|---|
| `Value` | recurse on `->value()` |
| non-falsy `ArrayableString` family (`ArrayableLink`, `LabeledValue`, Code, dictionary item) | `new ArrayableValue($value)` |
| falsy `LabeledValue` | typed `->value()` scalar |
| other falsy `ArrayableString` | `(string)` scalar |
| query builder (`Compare::isQueryBuilder`) | recurse on `->get()` |
| `Augmentable` or `Values` | `new Content($value)` |
| `AugmentedCollection` / Laravel `Collection` | `wrapArray(->all())` |
| `array` | `wrapArray` |
| anything else | untouched |

`wrapArray`: `array_is_list` → `array_map(wrap)` (plain iterable array); associative → `new Content`. Net shape contract: **single keyed thing = Content object, sequential list = plain array, scalar = scalar.** TagNode's loop guard, n:attr spreading, and modifier unwrapping all depend on this three-way rule.

### Content::wrapTopLevel — the ONLY place Deferred is created

Protected; called only from `wrapAll` — unit-test deferral policy through `Content::wrapAll` (as DeferredTest does), never by calling `wrapTopLevel` directly. A `Value` that `isRelationship()` AND has non-empty `raw()` becomes `new Deferred($value)`; everything else falls through to `wrap`.

- **Why only non-empty**: a `Deferred` is an object, hence always truthy. Empty relationships must stay eagerly wrapped so they evaluate falsy and `{if $related}` renders the else branch. This is the load-bearing truthiness invariant.
- **Why `empty($value->raw())` and not `count()`**: raw values are entry/term/asset IDs (never `0`/`'0'`, so PHP's `empty('0')` false-positive can't fire), and for `max_items: 1` fields `raw()` may be a scalar ID, not an array.
- **Why only top level**: nested access is already lazy via `Content`; deferral exists solely because `wrapAll` would otherwise run every relationship query at render start.
- `isRelationship()`/`raw()` resolve the deferred closure (cheap) but do NOT augment or run queries.
- Non-relationship Values (markdown, bard, text) must NEVER be deferred — their eager evaluation gives Latte correct scalar/truthiness semantics.

### ArrayableValue semantics

`ArrayableValue` (src/Data/ArrayableValue.php) preserves Statamic's dual string/structured values without forcing Latte authors back to array syntax. It wraps non-falsy `ArrayableString` instances and delegates polymorphically to their own `toArray()` implementation:

- echo/`__toString()` preserves the native scalar (`$link` → URL, select → selected value, Code → code text)
- `__get`/`ArrayAccess` expose structured keys (`$link->title`, `$choice->label`, `$code->mode`), recursively normalized through `Content::wrap` and cached per key
- `__call` forwards source methods (`url()`, `value()`, `label()`, `data()`) and wraps returns; writes throw `LogicException`
- `jsonSerialize()` delegates to the source, preserving native shallow Link serialization
- `source()` returns the native `ArrayableString`; `scalar()` reproduces the addon's previous normalized scalar (`LabeledValue` keeps its typed value, all others string-cast)
- falsy values are NEVER proxied because PHP objects are always truthy; they retain their old scalar shape so `{if}` remains correct

A non-empty proxy prints like a string but is still an object: strict identity against a scalar fails unless explicitly cast. This trade-off is required for arrow-property access.

### Content::unwrap — the inverse

`Content` → `->source()` (raw Augmentable/Values/array); `Deferred` → `unwrap(materialize())` (materialize-then-unwrap, **one semantic everywhere** — returning the raw `Value` would fail Latte's `is_array()` check in compiled n:attr code); `ArrayableValue` → `->scalar()` (preserves modifier/Antlers/n:attr compatibility); arrays mapped recursively; everything else untouched.

### Content instances

- Per-key lazy: `__get`/`offsetGet` → `resolve($key)` → `rawValue($key)` → `static::wrap`, memoized in `$cache`. For Augmentables, `rawValue` uses `augmentedValue($key)` so only the touched field is augmented.
- `__call` forwards to the source object (custom entry-class methods like `$page->events()`), wrapping the return via `Content::wrap`. Destructive methods (`Content::GUARDED_METHODS`, lowercase list: delete/save/set/merge/move/...) throw `LogicException`; unknown methods throw `BadMethodCallException`; array-backed sources never forward. `Deferred::__call` delegates to the materialized Content (single-item shape only).
- Read-only: `offsetSet`/`offsetUnset` throw `LogicException`. Templates never mutate view data.
- Iterating a Content backed by an Augmentable forces FULL augmentation of every key (`getIterator` resolves all `keys()`) — documented opt-in cost; property access stays per-key lazy.
- `keys()` calls `Augmented->keys()` which lives on `AbstractAugmented`, not the contract — hence the sanctioned `@phpstan-ignore method.notFound`. Don't "fix" the type; it's an upstream interface gap.

## Deferred semantics

`Deferred` (src/Data/Deferred.php, final class) proxies one non-empty relationship `Value`. Untouched variables never pay the query — that is the entire perf win.

- `materialize()`: `Content::wrap($this->value)` on first call, cached in `$resolved`/`$isResolved`. Two result shapes: **plain array of Content** (list field) or **single Content** (`max_items: 1`). Every touch surface must handle both.
- Touch surfaces (all materialize): `__get`, `offsetGet`, `__isset`/`offsetExists`, `getIterator`, `count`, `jsonSerialize`, `__toString`, and `Content::unwrap`.
- `count()` ALWAYS materializes. A raw-ID fast path was tried and removed: augmentation drops unpublished/deleted entries, so a raw count could exceed what `{foreach}` yields. Tail case: non-array, non-Countable materialization returns `$resolved === null ? 0 : 1`.
- `jsonSerialize()` returns `Content::unwrap($this->materialize())` — returning bare Content objects violates the JsonSerializable contract and emits `[{},{}]`.
- `__toString()` prints only scalar/Stringable materializations, else `''` — never fatals on an object cast. Echoing a relationship was never a supported pattern.
- `__isset`/`offsetExists`: delegates to `isset($resolved[$offset])` when the materialization is an array or ArrayAccess (Content is), returns `false` for scalar materializations. This is what `{ifset}`/`isset()` in templates hit — and it materializes, so an `{ifset $related}` pays the query.
- `source()` returns the underlying `Value` — used by `Resolver::actual` as a cheap pre-peel.
- Read-only like Content: writes throw `LogicException`.

**Invariant**: `Deferred` is created in exactly one place (`Content::wrapTopLevel`) and never appears in tag output, nested wraps, or filters — `TagNode`'s loop guard and every consumer assume this.

## Resolver

`Resolver` (src/Data/Resolver.php) peels wrappers down to final values. It never produces wrappers.

- `actual(...$values)`: first-non-null coalesce over the arguments (templates rely on `resolve($a, $b)` as a coalesce — do not "simplify" to single-arg). Per value: `Deferred` → `->source()` and `ArrayableValue` → `->scalar()` pre-peels, then a do/while loop over Statamic core's `Statamic\View\Blade\value()` (imported `as statamic_value`; a namespaced function autoloaded via composer `files` — future upstream wrapper types are handled for free) plus `Compare::isQueryBuilder` → `->get()` (Statamic's helper does NOT resolve query builders). The loop runs until stable because one unwrap can expose another wrapper (Value → ArrayableString); the `is_object($previous) || is_object($value)` guard bounds it since `statamic_value` only peels objects. All-null returns `$values[0] ?? null`.
- `drill($value, ...$keys)`: `actual()` first, then walks each key (dot-notation segments supported), re-resolving via `actual()` after every step. `get()` tries array/ArrayAccess index, then property isset, then `method_exists` call, else null.
- Template exposure (src/Latte/Extensions/ResolverExtension.php): **functions** `resolve` and `r` map to `Resolver::actual` (coalesce); the **filter** `resolve` maps to `Resolver::drill` (drills into keys: `{$val|resolve:'author','name'}`). Easy to confuse: function coalesces, filter drills.
- Global helper: `resolve_value(...)` in src/helpers.php, a `function_exists`-guarded wrapper around `Resolver::actual`.
- `Compare` is a facade — unit tests exercising Resolver need the addon TestCase bootstrapped.
- Codec divergence to know: a raw non-string-backed `ArrayableString` passed directly to `Resolver` resolves via Statamic's `->value()`, while an `ArrayableValue` created at the render boundary resolves through `->scalar()` to preserve its prior template scalar. Intentional; don't "align" them.

## Normalizer: deprecated shim + THE RENAME HAZARD

`src/Data/Normalizer.php` delegates `data`/`normalize`/`unwrap` to `Content::wrapAll`/`wrap`/`unwrap`. It exists ONLY because already-compiled Latte templates in users' cache dirs bake the old FQCN as string literals, and Latte's compile cache does not invalidate when addon PHP changes. Deleting it before the next major (3.0) fatals every stale compiled template with "class Normalizer not found". When removing it in 3.0: also delete the "deprecated Normalizer shim" describe block in tests/Feature/ContentWrapTest.php and note in the changelog that users must clear compiled views.

**Rename hazard — read before renaming ANY Data or Support class.** Compiled-template PHP bakes FQCN strings inside `print()` methods as plain string literals; an IDE rename will NOT catch them. As of this writing, SIX files emit `\Daun\StatamicLatte\...` literals into compiled code:

- src/Latte/Extensions/AttributeNormalizationExtension.php (`Content::unwrap`)
- src/Latte/Extensions/Nodes/AntlersNode.php (`Content::unwrap`)
- src/Latte/Extensions/Nodes/TagNode.php (`Tags::fetch`, `Tags::fetchWithContent`, `Content` instanceof guard, `Tags::stringifyResult`)
- src/Latte/Extensions/Nodes/CacheNode.php (`Support\Cache::class`)
- src/Latte/Extensions/Nodes/SectionNode.php (`Sections::store`)
- src/Latte/Extensions/Nodes/YieldNode.php (`Sections::placeholder`)

(NocacheNode additionally bakes the upstream `"Statamic\StaticCaching\NoCache\BladeDirective"` string.) **Do not trust this list — re-derive it.** After any rename, run:

```
grep -rn 'StatamicLatte' src/ | grep -v 'namespace\|use '
```

and inspect every `print()` method emitting FQCN string literals. Any class whose FQCN lands in compiled output is frozen in users' compile caches: never rename it without leaving a shim (the Normalizer precedent).

## Modifiers get [] as context — on purpose

`ModifierExtension::applyModifier` calls `($this->loader->load($name))($value, $args, [])`. The empty third argument is a deliberate contract from PR #7 (commit 477db00, released 1.2.1): modifiers run context-free in Latte; before the fix, modifiers that destructure context crashed. Do NOT "helpfully" pass the cascade — that would silently change modifier behavior. (CHANGELOG 1.2.1 says "default context"; it means an empty default, not a populated cascade.)

## Recipes

### Add handling for a new Statamic value type to the codec

1. Add the `instanceof` branch in `Content::wrap` at the correct codec position: wrapper-peeling branches (Value/LabeledValue/ArrayableString/query builder) first, object-producing branches (Augmentable/Values/collections) after.
2. If templates will hand the value back to Statamic (modifiers, antlers), add the mirror peel in `Content::unwrap`.
3. Do NOT touch `Resolver` for shapes — it must never produce wrappers. If the new type is a wrapper Statamic core should peel, it's usually handled upstream for free by `statamic_value`; only add a pre-peel in `Resolver::actual` (like the Deferred one) for addon-local types.
4. Tests in tests/Feature/ContentWrapTest.php: a direct unit assertion on `Content::wrap` AND a `$this->latte('...', [...])` render (tests/Concerns/InteractsWithLatteViews.php::latte writes a temp .latte file and renders through the full engine stack).

### Change Deferred touch-surface behavior / deferral policy

1. Deferral policy changes go ONLY in `Content::wrapTopLevel`. Keep both preconditions: emptiness decidable from `->raw()` without augmenting, and only-defer-non-empty (truthiness).
2. New touch surfaces on `Deferred` must delegate through `materialize()` and handle BOTH shapes (plain array and single Content). Never expose the raw ID list to counting or iteration.
3. Tests in tests/Feature/DeferredTest.php: extend the truthiness matrix (empty → else branch, non-empty → truthy, foreach, `|length`, `|resolve`, antlers crossing, json) and assert laziness via the file's `isResolved()` reflection helper — untouched proxies must stay unresolved after render. No existing test asserts post-render laziness (the Deferred is created inside `NormalizingEngine::get`, unreachable from the test); to do it, build the proxy yourself — `$deferred = Content::wrapAll(['related' => childPage()->augmentedValue('related_pages')])['related']` — pass `$deferred` into `$this->latte()` as view data (`wrapAll` passes an existing Deferred through untouched), then assert `isResolved($deferred)` is still false after render. Fixture fields: `related_page` (entries, max_items: 1) and `related_pages` (entries list) in tests/fixtures/blueprints/collections/pages/page.yaml.
4. Simulate the render boundary without a full request: `$entry->augmentedValue('related_pages')` produces exactly the deferred Value the cascade would deliver; push it through `Content::wrapAll`.

### Debug a truthiness/emptiness bug in a template

1. First question: did the value cross the boundary **wrapped or raw**? Data through `NormalizingEngine::get` is wrapped; data passed directly to a modifier/antlers block should have been unwrapped at the exit. A raw `Value` or `Deferred` leaking into an `{if}` is always truthy.
2. Check the shape: `{if $x}` on an empty relationship must be an eager `[]`/null (falsy) — if it's a `Deferred`, the non-empty precondition in `wrapTopLevel` broke.
3. Reproduce with the one-liner helper: `$this->latte('{$related|length}', ['related' => $entry->augmentedValue('related_pages')])`.
4. Escape hatches for dumping (a bare Content dumps empty because everything is lazy): `$content->source()`, `$deferred->source()`, `Content::unwrap($x)`.
5. If behavior is inexplicable across runs (e.g. old FQCNs firing), delete the compiled view cache (`config('view.compiled')` path) — stale compiled templates are a known failure mode and the reason the Normalizer shim exists.
6. Laziness checks via reflection: `Deferred::$isResolved` (pattern in DeferredTest::isResolved) and `Content::$cache` (pattern in ContentWrapTest "augments only the accessed field").

## Pitfalls

- Pest test files share a global namespace: `testEntry()`/`nestedEntry()` (ContentWrapTest) and `childPage()`/`plainPage()`/`isResolved()` (DeferredTest) are taken — new test files must not redeclare them.
- Full top-level laziness (deferring ALL values) is a rejected design (plan §7), not an oversight: wrapper objects are always truthy and would break `{if}` on empty scalar fields.
- `json_encode` on a relationship now emits real augmented data; pre-refactor it emitted `[{},{}]`. Improvement, but not byte-for-byte parity — documented in CHANGELOG Notes.
- `NormalizingEngine` registration must stay inside `$this->app->booted()` in `ServiceProvider::installEngine` — registering earlier loses the race with Miko's own `latte` engine registration.

## How to verify a change

```
./vendor/bin/pest tests/Feature/ContentWrapTest.php tests/Feature/DeferredTest.php tests/Feature/ResolverTest.php tests/Feature/EngineDelegationTest.php
composer test          # full Pest suite (~19s)
composer analyse       # PHPStan (only sanctioned ignore here: method.notFound on Augmented->keys())
composer lint          # pint --test
```

Every commit must leave all three gates green. Targeted runs: `composer test -- --filter=Deferred` (or ContentWrap / Resolver / EngineDelegation).

## Related skills

- **orientation** — repo layout, docs index
- **tag-bridge** — Tags::fetch wrap boundary, TagNode loop guard
- **extensions-and-nodes** — the compiled-code emission sites listed in the rename hazard
- **template-syntax** — resolve()/|resolve usage from the template author's side
- **testing** — the latte() helper, fixtures, Pest conventions
- **debugging** — compile-cache staleness, reflection patterns
- **quality-gates** — composer test/analyse/lint expectations
- **caching** — CacheNode/NocacheNode, which also bake FQCNs
