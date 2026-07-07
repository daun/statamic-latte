# Plan: Rebase the data layer on Statamic primitives (Plan B)

Implementation plan for consolidating the addon's data abstractions (`Content`,
`Normalizer`, `Resolver`) into a single lazy view-model that delegates the
Statamic-specific unwrapping to Statamic core, and for fixing eager
relationship augmentation at the render boundary.

This document is self-contained — it assumes no prior conversation context.

---

## 1. Background & goal

This addon integrates the Latte templating engine into Statamic. It currently
maintains three bespoke data classes:

- `src/Data/Content.php` — lazy read-only wrapper around a keyed source
  (Statamic `Augmentable`, `Statamic\Fields\Values`, or assoc array). Gives
  templates uniform `->key` / `['key']` access with per-key caching.
- `src/Data/Normalizer.php` — converts Statamic data into template shapes
  (keyed thing → `Content`, list → plain array, scalar → itself) and provides
  the inverse `unwrap()` for handing data back to Statamic.
- `src/Data/Resolver.php` — unwraps Statamic wrapper types (`Value`,
  `LabeledValue`, `ArrayableString`, query builders, `FluentTag`, `Modify`)
  to their final value; powers the `resolve()` / `r()` template functions,
  the `|resolve` filter, and the `resolve_value()` PHP helper.

Research against `vendor/statamic/cms` (v6.21) established:

1. **`Resolver::actual()` duplicates Statamic core.** Statamic 6 ships
   `Statamic\View\Blade\value()` (`vendor/statamic/cms/src/View/Blade/helpers.php:13`,
   composer-autoloaded via `"files"`), with an almost identical unwrap chain.
   Ours should delegate, so future Statamic wrapper types are handled upstream
   for free. (Statamic's helper does *not* resolve query builders — we keep
   that part.)
2. **`Content`/`Normalizer` are NOT plain duplication.** They fix real holes in
   Statamic's native objects that matter more in Latte than in Blade:
   object truthiness (`{if $subtitle}` on a deferred `Value` object is always
   true), `Value::__get` hitting unresolved query builders on relationship
   fields, no recursion in `Values`, no augmentation caching
   (`Value::value()` re-runs `fieldtype->augment()` on every access —
   `vendor/statamic/cms/src/Fields/Value.php:72-94`). These classes stay, but
   get consolidated.
3. **Performance bug:** `NormalizingEngine::get()` → `Normalizer::data()`
   eagerly normalizes every top-level template variable. Statamic's cascade
   delivers every blueprint field as a *deferred* `Value`
   (`Cascade::hydrateContent` → `toDeferredAugmentedArray()`); our eager pass
   calls `->value()` on each one and executes every relationship field's
   query builder (`Normalizer::normalize()` → `Compare::isQueryBuilder` →
   `->get()`) at render start, whether or not the template uses the field.
   Blade/Antlers pay none of this.

**Goal:** three classes → one template-facing class (`Content`) with two
boundary statics, `Resolver` gutted to a delegate, and relationship fields
deferred at the top level — with **zero template-facing behavior changes**
except where explicitly listed in §6.

## 2. Required context (read before starting)

Addon files (all under `/Users/philipp/Projects/statamic-latte`):

- `src/Data/Content.php`, `src/Data/Normalizer.php`, `src/Data/Resolver.php`
- `src/Latte/NormalizingEngine.php` (calls `Normalizer::data` at line 25)
- `src/Latte/Support/Tags.php` (calls at lines 117, 135, 180)
- `src/Latte/Extensions/ModifierExtension.php` (unwrap at lines 52–53)
- `src/Latte/Extensions/AttributeNormalizationExtension.php` (emits the FQCN
  string `\Daun\StatamicLatte\Data\Normalizer::unwrap(` into **compiled
  template code**, line 49)
- `src/Latte/Extensions/Nodes/AntlersNode.php` (same — FQCN inside a compiled
  code string, line 45)
- `src/Latte/Extensions/Nodes/TagNode.php` (loop guard
  `! $ʟ_result instanceof Content`, ~line 134)
- `src/helpers.php` (`resolve_value()`)
- `src/Latte/Extensions/ResolverExtension.php`
- Tests: `tests/Feature/NormalizerPrototypeTest.php`,
  `tests/Feature/ResolverTest.php`, `tests/Feature/NAttributeTest.php`,
  `tests/Feature/TagTest.php`, `tests/Feature/ModifierTest.php`

Vendor references:

- `vendor/statamic/cms/src/View/Blade/helpers.php` — the `value()` helper to
  delegate to
- `vendor/statamic/cms/src/Fields/Value.php` — note `value()` (no augment
  cache), `isRelationship()` (line 226, resolves raw but does NOT augment),
  `raw()`, `__get`
- `vendor/statamic/cms/src/Fields/Values.php`
- `vendor/statamic/cms/src/View/Cascade.php` — `hydrateContent()` shows what
  the engine receives at the top level

Commands: `composer test` (Pest), `composer analyse` (PHPStan),
`composer lint` / `composer format` (Pint).

## 3. Step 1 — Gut `Resolver` to delegate to Statamic

**Keep** the `Resolver` class, its public API (`actual()`, `drill()`), the
`ResolverExtension` registrations, and `resolve_value()` — they are documented
public surface. Replace the hand-rolled unwrap chain inside `actual()`:

```php
use function Statamic\View\Blade\value as statamic_value;

public static function actual(...$values): mixed
{
    foreach ($values as $value) {
        // Delegate wrapper peeling to Statamic; loop until stable because
        // one unwrap can expose another wrapper (e.g. a Value whose
        // augmented value is an ArrayableString).
        do {
            $previous = $value;
            $value = statamic_value($value);
            if (Compare::isQueryBuilder($value)) {
                $value = $value->get();
            }
        } while ($value !== $previous && (is_object($previous) || is_object($value)));

        if (isset($value)) {
            return $value;
        }
    }

    return $values[0] ?? null;
}
```

`drill()` and `get()` stay as-is (they only call `actual()`). Remove the now
unused imports (`Value`, `LabeledValue`, `ArrayableString`, `Values`,
`FluentTag`, `Modify`) — keep `Compare`.

**Intentional behavior change (document in CHANGELOG):** a bare
`ArrayableString` now resolves via `->value()` instead of `(string) $value`.
For string-backed fields (the normal case, and what `ResolverTest` asserts)
the result is identical; for non-string-backed `ArrayableString` (e.g. a
select with boolean values) the underlying value is now returned instead of a
string cast — arguably more correct.

**Acceptance criteria**

- `tests/Feature/ResolverTest.php` passes **unchanged**.
- `Resolver::actual()` contains no `instanceof Value/LabeledValue/
  ArrayableString/Values/FluentTag/Modify` checks of its own.
- New unit test: `Resolver::actual(new Value(fn () => new ArrayableString('x')))`
  returns `'x'` (proves the loop-until-stable behavior; the old single-pass
  Statamic helper alone would return the `ArrayableString`).

**Gotchas**

- `Statamic\View\Blade\value()` is a namespaced *function*; import with
  `use function`. It's autoloaded (statamic/cms composer `"files"`), no boot
  needed — but `Compare` is a facade, so any new unit tests still need the
  addon `TestCase`.
- `LabeledValue extends ArrayableString`, so Statamic's single
  `instanceof ArrayableString` check covers both — no separate branch needed.
- Do not "simplify" the multi-argument first-non-null semantics; templates use
  `resolve($a, $b)` as a coalesce.

## 4. Step 2 — Fold `Normalizer` into `Content`

Move the four static methods onto `Content` with clearer names:

| Old | New |
|---|---|
| `Normalizer::normalize($v)` | `Content::wrap($v)` |
| `Normalizer::data($data)` | `Content::wrapAll($data)` |
| `Normalizer::normalizeArray($a)` (protected) | protected `Content::wrapArray($a)` |
| `Normalizer::unwrap($v)` | `Content::unwrap($v)` |

Note the name collision: `Content` already has an **instance** method
`unwrap()` (the escape hatch returning `$this->source`). PHP does not allow a
static and an instance method with the same name. Resolution: rename the
instance method to `source()` and update its one external caller semantics —
`Content::unwrap($content)` (static) should do
`$value instanceof Content ? $value->source() : …` exactly as
`Normalizer::unwrap` does today. Grep for `->unwrap()` usages (tests included)
and update.

Then:

1. Update call sites to the new statics:
   - `src/Latte/NormalizingEngine.php:25` → `Content::wrapAll($data)`
   - `src/Data/Content.php:92` (self-recursion) → `static::wrap(...)`
   - `src/Latte/Extensions/ModifierExtension.php:52-53` → `Content::unwrap`
   - `src/Latte/Support/Tags.php:117,180` → `Content::wrap`; line 135 →
     `Resolver::actual(Content::unwrap($result))`
   - `src/Latte/Extensions/AttributeNormalizationExtension.php:49` → change
     the emitted string to `'\Daun\StatamicLatte\Data\Content::unwrap('`
   - `src/Latte/Extensions/Nodes/AntlersNode.php:45` → same FQCN swap inside
     the format string
2. Replace `src/Data/Normalizer.php` with a thin **deprecated shim** whose
   four statics delegate to `Content` (`@deprecated use Content::wrap()` …).
   Do NOT delete it: the two compiler extensions bake the old FQCN into
   *compiled template files*. Latte's compile cache does not invalidate when
   addon PHP changes, so an existing site upgrading the addon would fatal with
   "class Normalizer not found" on every already-compiled template without the
   shim. Remove the shim in the next major only.
3. Tests: update `tests/Feature/NormalizerPrototypeTest.php` to the new API
   (consider renaming to `ContentWrapTest.php`), and add one small test that
   the deprecated `Normalizer::normalize/unwrap/data` still delegate
   correctly.

**Acceptance criteria**

- Full suite green; no test besides the renamed/updated ones changed meaning.
- `grep -rn "Normalizer::" src/` returns only the shim file itself.
- The n:attr test (`NAttributeTest` "spreads an associative array passed as a
  Content object") and the Antlers interop tests in `NormalizerPrototypeTest`
  still pass — these exercise the two baked-FQCN sites.
- PHPStan (`composer analyse`) clean; Pint (`composer lint`) clean.

**Gotchas**

- The compiled-code strings are plain string literals — the FQCN swap won't be
  caught by an IDE rename. Grep for `Daun\\StatamicLatte\\Data\\Normalizer`
  across `src/` after the change.
- Tests compile templates into the temp view namespace; if anything behaves
  strangely across runs, clear Latte's compiled cache directory (the
  `view.compiled` path) — stale compiled templates referencing old FQCNs are
  exactly what the shim guards against.
- `Content::wrap()` must keep the exact normalization order from
  `Normalizer::normalize()` (Value → LabeledValue → ArrayableString → query
  builder → Augmentable/Values → collections/arrays). Do not merge this chain
  with `Resolver::actual()`: `wrap()` recurses into `Value->value()` results
  and produces `Content` objects; `actual()` must never produce `Content`.
  They are different codecs for different directions.

## 5. Step 3 — Defer relationship augmentation at the render boundary

**This step is independent of steps 1–2, is the riskiest, and should be its
own commit (or PR) so it can be reverted alone.**

### Problem

`Content::wrapAll()` (ex-`Normalizer::data`) runs at every render over the
whole cascade. For each deferred `Value` it calls `->value()`; for
relationship fieldtypes (entries, terms, assets, users…) that returns a query
builder which `wrap()` immediately executes via `->get()` and then augments —
for every relationship field in the blueprint, used or not.

We cannot simply defer everything behind wrapper objects: plain PHP locals
can't be lazy, and a wrapper object is always truthy, which would break
`{if $related}` on empty fields. The design that preserves semantics:

**Emptiness is decidable from the raw value.** `Value::raw()` /
`Value::isRelationship()` resolve the deferred closure but do **not** run
augmentation or queries (verify in `vendor/.../Fields/Value.php:45-70,226-231`
— `resolve()` only unwraps the closure to the raw stored data, i.e. IDs).

So in `wrapAll()` **only** (top level — nested access is already lazy via
`Content`), for each `Value`:

- `! $value->isRelationship()` → normalize eagerly, as today.
- `isRelationship()` and `empty($value->raw())` → normalize eagerly (cheap:
  augmenting an empty relationship is trivial and yields the correct **falsy**
  `[]`/`null`).
- `isRelationship()` and raw non-empty → wrap in a new `Deferred` object.
  Truthiness is correct *because we only defer non-empty values*.

### `Deferred` class (`src/Data/Deferred.php`)

A small transparent proxy that materializes on first touch:

```php
final class Deferred implements ArrayAccess, Countable, IteratorAggregate
{
    private mixed $resolved;
    private bool $isResolved = false;

    public function __construct(private Value $value) {}

    private function materialize(): mixed
    {
        if (! $this->isResolved) {
            $this->resolved = Content::wrap($this->value);
            $this->isResolved = true;
        }
        return $this->resolved;
    }
    // __get / offsetGet / offsetExists / getIterator / count / __isset
    //   → delegate to materialize()
    // count(): if not yet resolved and the raw value is an array of IDs,
    //   return count($this->value->raw()) without materializing (cheap win
    //   for {$related|length}); otherwise count the materialized value.
    // offsetSet/offsetUnset → LogicException (read-only), mirroring Content.
    // source(): return the underlying Value (for unwrap()).
}
```

Notes on delegation: after materializing, the inner value is whatever
`Content::wrap` produces — a plain array (entries list) or a `Content`
(single-entry field with `max_items: 1`). `__get`/`offsetGet` must handle
both (array → `$resolved[$key] ?? null`, `Content` → forward). `getIterator`
must handle array (wrap in `ArrayIterator`) vs `Content` (forward to its
iterator).

### Boundary integration

- `Content::unwrap()` must learn `Deferred`: return
  `$value->source()` (the original `Value`) — Statamic modifiers, Antlers and
  n:attr all understand `Value` or will re-resolve it. Exception: for n:attr
  an un-materialized `Value` still fails Latte's `is_array()` check, so
  `unwrap(Deferred)` should return the *materialized then unwrapped* value
  instead: `Content::unwrap(Content::wrap-result)`. Pick ONE semantic:
  materialize-then-unwrap is the safe choice everywhere (modifiers like
  `|length` then behave exactly as today).
- `Resolver::actual()`: `Deferred` resolves like its source `Value` — add a
  `$value instanceof Deferred → $value->source()` pre-step before the
  delegation loop (or materialize; source is cheaper and equivalent).
- `TagNode`'s loop guard is unaffected: tag output goes through
  `Content::wrap`, never through `wrapAll`, so `Deferred` never appears there.
  Keep it that way — `Deferred` is created **only** in `wrapAll()`.

### Acceptance criteria

- New feature test (pattern exists in `NormalizerPrototypeTest`'s lazy-cache
  test using `ReflectionClass`): render a template that does **not** touch a
  non-empty entries field → assert the corresponding variable is a `Deferred`
  whose inner `Value` has not been augmented (reflection: `Deferred::$isResolved`
  is false). Render a template that *does* touch it → values correct.
- Truthiness matrix test, all at top level:
  - empty entries field: `{if $related}no{else}yes{/if}` → renders the else
    branch (falsy) — identical to before.
  - non-empty entries field: `{if $related}` truthy, `{foreach $related as $r}
    {$r->title}` works, `{$related|length}` returns the right count
    **without** a prior full materialization if implemented via raw IDs
    (nice-to-have; correctness first).
  - single-entry field (`max_items: 1`), non-empty: `{$author->name}` works.
- `{$related|resolve}`, `{s:…}` tags, `{antlers}` blocks and `n:attr` with a
  deferred variable behave as before (covered by existing suites + one new
  test each for modifier and antlers crossing with a `Deferred`).
- Existing full suite green with **no assertion changes** outside new files.

### Gotchas

- `isRelationship()` calls `resolve()` which runs the transient closure from
  `AbstractAugmented::transientValue` — cheap (raw data + fieldtype lookup),
  but it does resolve the closure. That is fine and unavoidable.
- Do NOT defer non-relationship `Value`s (markdown, bard, etc.): their eager
  evaluation is what gives Latte correct scalar semantics (`{if}`, `??`,
  string comparison). Deferring them reintroduces the Blade truthiness gotcha.
- `{$related}` echoed directly (no property access): today this echoes
  whatever the eager normalization produced (array → Latte throws or prints
  notice-ish output). Don't add `__toString` to `Deferred` to "fix" printing —
  matching today's behavior is out of scope; just make sure the error/output
  is not *worse*. If Latte fatals on echoing an object where it previously got
  an array, add `__toString` that materializes and delegates to
  `Tags::stringifyResult`-style printing.
- `json_encode($related)` in templates: add `JsonSerializable` delegating to
  the materialized value to keep parity with arrays/Content
  (`Content` itself does not implement it — parity with `Content` is
  acceptable; parity with today's plain array is not achievable — note it in
  the CHANGELOG).
- Empty-check edge: `Value::raw()` for a `max_items: 1` entries field may be a
  scalar ID, not an array — use `empty()` not `count()`.

## 6. Step 4 — Docs, changelog, cleanup

- README: no template-facing syntax changes, but update the internals section
  if it names `Normalizer` (grep README + `docs/` for class names).
- CHANGELOG entries: Resolver delegation (+ `ArrayableString` edge-case
  change), `Normalizer` deprecated in favor of `Content::wrap/wrapAll/unwrap`,
  `Content->unwrap()` instance method renamed to `source()`, deferred
  relationship augmentation (perf) with the `json_encode` caveat.
- `composer format` (Pint), `composer analyse` (PHPStan), `composer test` —
  all clean.

## 7. Explicit non-goals

- No changes to `NormalizingEngine`'s render flow, `Sections`/yield handling,
  `TagExtension`/`TagNode` semantics, or the `AttributeNormalizationExtension`
  pass structure (only the emitted FQCN string changes).
- No upstream PR to statamic/cms in this pass (a recursive/normalizing mode
  for `Statamic\Fields\Values` is worth proposing later; this refactor
  positions `Content` to adopt it if it lands).
- No attempt at full top-level laziness — incompatible with correct truthiness
  for plain PHP locals (see §5 rationale).
- Do not merge `Resolver` into `Content`: `actual()` (peel to final value,
  never produce wrappers) and `wrap()` (produce template wrappers) are
  opposite directions; keeping them separate is deliberate.

## 8. Suggested commit sequence

1. `refactor: delegate Resolver::actual to Statamic's Blade value() helper`
2. `refactor: fold Normalizer into Content as wrap/wrapAll/unwrap statics`
   (includes the deprecated shim + test updates)
3. `perf: defer augmentation of non-empty relationship fields at render boundary`
4. `docs: changelog + internals docs for data-layer rebase`

Each commit must leave the suite green (`composer test && composer analyse && composer lint`).
