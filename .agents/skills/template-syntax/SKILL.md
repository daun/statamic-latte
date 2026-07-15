---
name: template-syntax
description: "The user-facing contract of .latte templates in this addon — data access, s: tag spellings, arguments, modifiers, forms, antlers blocks, layouts, smart attributes. Load when answering \"why doesn't my template work\", writing example .latte templates or docs, or checking whether a template construct is supported."
---

# Template syntax: what .latte authors can write and what it means

This skill is the authoritative distillation of the template-author contract. README.md is the published spec; this skill adds the precise semantics behind it (verified against tests). If README and this file ever disagree, treat it as a bug and reconcile.

## When to use this skill

- A user template misbehaves ("{if} always true", "foreach loops fields", "modifier does nothing").
- Writing or reviewing example templates, docs, or README changes.
- Deciding whether a construct is supported before implementing around it.

## Core concept 1: everything template-visible is shape-normalized

At the render boundary (`src/Latte/NormalizingEngine.php` → `Content::wrapAll`) every view variable is normalized into one of three shapes (`src/Data/Content.php::wrap`):

| Input | Template shape |
|---|---|
| Entry / Asset / Term / Values group / assoc array | `Content` object — `->key` and `['key']` both work |
| non-falsy Link / Select / Radio / Button Group / Code / dictionary value | stringable `ArrayableValue` — scalar when printed, structured keys via `->key` |
| Sequential list / query result / collection | plain PHP array — `{foreach}`-able, `count()`-able |
| Scalar / falsy structured-string value / unknown object | untouched or reduced to its prior scalar |

WHY: raw Statamic `Value` objects are always truthy and re-augment on every access; `Content` fixes truthiness, caches per key, and augments lazily (only the fields you touch — verified in tests/Feature/ContentWrapTest.php "augments only the accessed field").

Rules that follow:

- `$entry->title` and `$entry['title']` are equivalent (ContentWrapTest "supports both -> and [] access"). Method calls pass through to the source object: `$entry->slug()` calls `Entry::slug()` and wraps the result — custom entry-class methods work (`$page->events()`). Destructive methods (save/delete/set/...) throw `LogicException`; unknown methods throw `BadMethodCallException` (unlike unknown fields, which return null).
- `Content` is read-only for array-style writes: `$entry['k'] = ...` / `unset($entry['k'])` throw `LogicException('Content wrappers are read-only.')`. Property writes (`$entry->k = ...`) are NOT guarded — they silently create a dynamic property that shadows the real field on later reads. Treat both as forbidden.
- Nested access chains lazily: `{$entry->author->name}` returns nested `Content` objects.
- Iterating a `Content` (`{foreach $entry as $k => $v}`) walks ALL its fields and forces full augmentation — legal but expensive; almost never what a template author wants.
- Tag output goes through `Content::wrap` too (`src/Latte/Support/Tags.php::fetchTag`), so `{s:...}` results have identical shapes to view data.

Statamic's `ArrayableString` family acts like a scalar when printed but exposes extra data in Antlers. Latte gets equivalent data through object syntax:

```latte
{$link}          {* URL *}
{$link->url}
{$link->title}   {* linked Entry/Asset field *}
{$link->alt}     {* linked Asset field *}
{$choice->label} {* Select/Radio/Button Group label *}
{$code->mode}    {* Code field mode *}
```

`ArrayableValue` also retains `[]` access and forwards native methods (`$link->url()`, `$link->value()`). Missing properties return null; writes throw `LogicException`. Falsy values remain scalars because PHP objects are always truthy, preserving `{if $value}` behavior. A non-empty value is an object despite printing like a string, so strict scalar identity requires `(string) $value`.

## Core concept 2: relationship fields and the Deferred proxy (the #1 confusion source)

Top-level view variables that are **non-empty relationship fields** (entries, terms, assets, users) become a `Deferred` proxy (`src/Data/Deferred.php`), created ONLY in `Content::wrapTopLevel`. The query + augmentation runs at first touch, never before. The exact contract, pinned by tests/Feature/DeferredTest.php:

- `{if $related}` is CORRECT both ways. A `Deferred` object is always truthy — that is safe *because only non-empty relationships are deferred*. Empty relationships are eagerly wrapped to a falsy `[]`/`null`, so `{if $related}...{else}...{/if}` picks the right branch in both cases. Do not "optimize" this; it is the load-bearing invariant.
- `count($related)` and `{$related|length}` materialize and count the **augmented** result. This can be LESS than the number of stored IDs: augmentation drops unpublished/deleted entries. The count always equals what `{foreach}` yields.
- `{foreach $related as $r}` iterates entries as `Content` objects.
- Single-item fields (`max_items: 1`) materialize to a single `Content`: `{$author->title}` works directly.
- `json_encode($related)` / `|json` emits real entry data (not `[{},{}]`).
- Echoing a relationship (`{$related}`) prints `''` for non-scalar values — silent, never a fatal. It was never a supported pattern.
- `{ifset $related}` / `isset($related)` tests variable existence, NOT emptiness — and the answer depends on field shape: an empty multi-item relationship wraps to `[]` (set → `ifset` true), an empty `max_items: 1` field wraps to `null` (not set → `ifset` false). Never branch on `{ifset}`; use `{if $related}`, which is correct for every shape.
- Index access materializes: `$related[0]->title` works; `isset($related['x'])` on a single-item shape delegates to Content, and returns `false` if the field materialized to a scalar.

`Deferred` appears ONLY for top-level view variables. Tag results and nested fields never contain one.

## Core concept 3: no key hoisting

Unlike Antlers, field keys are never hoisted into scope. Inside a `{s:tag}` pair the current item is `$value`; fields are accessed explicitly:

```latte
{s:collection:pages}
  {$value->title}
{/s:collection:pages}
```

Inside a plain `{foreach}` you name the variable yourself (`{foreach $entries as $entry}`). A bare `{title}` is a Latte syntax error, not a field lookup.

## Core concept 4: three spellings for Statamic tags

1. **Block form** `{s:tag ...}...{/s:tag}` and self-closing `{s:tag .../}` — for rendering. Statamic-style args (`in: pages`, nested keys). `$iterator`, `{sep}`, `{first}`, `{last}` work inside pairs (real Latte foreach). Only tags registered at boot compile as blocks.
   - A single NON-closed `{s:link to: "x"}` is NOT supported (Latte 3 can't register a tag as both single and paired — nette/latte#382, see the commented-out test at the top of tests/Feature/TagTest.php). Always self-close: `{s:link to: "x" /}`.
2. **Expression form** `(s:tag ...)` — for assignment, conditions, filters, `n:` attributes: `{var $entries = (s:collection from: pages)}`, `{if (s:collection:count in: pages) > 1}`. Same Statamic-style args. Works even for tags registered after boot (rewritten to a runtime `s()` call). Caveat: bare filter pipes in params throw at compile time (`in: $c|lower` → "Bare filters are not supported"); parenthesize: `in: ($c|lower)`.
3. **Function form** `s(...)` / `statamic(...)` — plain Latte function call with Latte-native argument syntax (tests/Feature/HelperTest.php): `{s(link, to: "fanny-packs")}` (bareword name + named args) or `s("collection", ["from" => "pages", "title:contains" => "Layout"])` (nested keys as quoted array keys). Use when you want ordinary PHP call semantics, e.g. `{foreach s("collection", [...]) as $entry}`.

Special params consumed by the bridge, never forwarded to the tag:

- `as: entries` — binds the raw result to `$entries` inside the pair body (rendered exactly once, no auto-foreach). The alias must be a LITERAL name; `as: $var` is forwarded to the tag instead.
- `content: $expr` — hands a pre-rendered string to body-transforming tags (`{s:widont content: $entry->headline /}`), typically built with `{capture}`.

## Core concept 5: argument syntax

Nested parameter keys work with both `:` and `=>` separators, and values accept literals, variables, and any Latte expression (README Arguments, tests/Feature/TagTest.php "params"):

```latte
{var $a = (s:collection from: pages, status:is => draft)}
{var $b = (s:collection from: pages, title:contains:Christmas)}
{var $c = (s:collection from: pages, title:contains: $request->title)}
```

Rule of thumb: the LAST colon of a bareword chain is the key/value separator; earlier colons stay in the key.

## Core concept 6: pagination

Paginated tags return a live Laravel paginator (items wrapped as `Content`). Loop it directly; meta comes from paginator methods, not separate variables:

```latte
{s:collection:pages as: entries, paginate: 10}
  {foreach $entries as $entry}{$entry->title}{/foreach}
  Page {$entries->currentPage()} of {$entries->lastPage()}, {$entries->total()} total
{/s:collection:pages}
```

Also works via capture: `{var $entries = (s:collection from: pages, paginate: 1)}` or `s("collection", [...])` (HelperTest "captures a paginator into a variable").

## Core concept 7: modifiers as filters

Every Statamic modifier is a Latte filter: `{$title|upper|truncate:50}`, chainable, usable in expressions (`{if ("ABC"|is_uppercase)}`). Two deliberate deviations from Antlers (`src/Latte/Extensions/ModifierExtension.php`):

- **Existing Latte filters win.** Modifiers are registered with `->except(existing filters)` — a name collision (e.g. a filter you added, or a Latte core filter) means the Latte filter runs, not the Statamic modifier (tests/Feature/ModifierTest.php "preserves existing filters").
- **Modifiers run with an EMPTY context** (third arg is `[]`, deliberately — PR #7, released 1.2.1). Modifiers that read cascade context behave differently than in Antlers, by design. Values and args are `Content::unwrap`-ed before the modifier sees them, so modifiers receive raw Statamic data.

## Core concept 8: resolve()/r() vs |resolve

Escape hatches for raw `Value`/`LabeledValue`/query-builder objects (`src/Latte/Extensions/ResolverExtension.php`):

- `resolve($a, $b)` / `r($a, $b)` → `Resolver::actual`: peels wrappers, returns the FIRST NON-NULL argument — it is also a coalesce. It never wraps its result in a `Content` — but it does not peel `Content` either: `resolve($content)` returns the Content unchanged (Content is the addon's own wrapper, not a Statamic one; peel with `Content::unwrap`). Edge case: if every argument resolves to null, the first argument is returned as-is, possibly still wrapped.
- `{$val|resolve:'author','name'}` → `Resolver::drill`: peels, then walks keys (dot notation allowed), re-resolving at each step.

Same name, different behavior: the *function* coalesces, the *filter* drills. Rarely needed — printing and property access already resolve automatically.

## Core concept 9: forms

The user contract (README "Forms", pinned by tests/Tags/FormTest.php):

- `form:create` returns **data, not `<form>` HTML**. Recommended idiom — capture with `as: form`, build the form yourself: `$form->attrs->method`, `$form->attrs->action`, loop `$form->fields` (`$field->handle`, `$field->display`, `$field->error`).
- `form:success` is a scalar (session message or null): `{s:form:success in: contact}{$value}{/s:form:success}`.
- `form:errors` is a **BOOLEAN GATE, not an iterator**. The pair body renders once when errors exist (`$value` is `true`), or is skipped. Individual error strings come from the `form:create` capture: `{foreach $form->errors as $e}` or `$form->error->name` (first error per field handle).
- `form:fields` and `form:set` do NOT work through the proxy (they depend on Antlers parsing) — `form:fields` throws, `form:set` outputs nothing. Use `$form->fields` and per-tag `in:` params instead.
- `user:login_form` / `register_form` / `forgot_password_form` compile through the proxy the same way (tests/Tags/UserFormsTest.php).

## Core concept 10: {antlers} inline blocks

```latte
{antlers}
    {{ collection:pages }}{{ title }}{{ /collection:pages }}
{/antlers}
```

- Reach for it for complex built-in tags with Antlers-only behavior, or to paste doc examples verbatim.
- Variable hand-off: ALL in-scope Latte variables (including `{var}` locals) are `Content::unwrap`-ed and passed to the Antlers view (`src/Latte/Extensions/Nodes/AntlersNode.php`) — Antlers sees raw Statamic data and does its own augmentation. Deferred relationships cross the boundary fine (DeferredTest "crosses back into Antlers").
- Content inside `{antlers}...{/antlers}` is protected from the `s:` rewrites — Statamic-looking syntax stays literal. An UNCLOSED block loses that protection.
- Takes no arguments; `n:antlers` is not supported (both throw `CompileException`).
- `{section}`/`{yield}` interoperate across Latte, Antlers and Blade (shared Statamic content store).

## Core concept 11: layout resolution (user-visible rule)

A `.latte` template's parent layout comes from entry data, exactly like Antlers: default `resources/views/layout.latte`, overridable per entry/collection with `layout: other_layout`. Implemented via Latte's `coreParentFinder` reading the cascade's `current_layout` param (`src/Latte/Extensions/LayoutExtension.php`); includes and embeds are exempt. Detail lives in the extensions-and-nodes skill. Verified end-to-end in tests/Feature/LayoutTest.php.

## Core concept 12: smart attributes (Latte 3.1+)

Type-aware attribute rendering works with wrapped data and `(s:...)` subexpressions (tests/Feature/SmartAttributesTest.php, tests/Feature/NAttributeTest.php):

```latte
<input type="checkbox" disabled={(s:collection:count in: pages)}>   {* truthy → bare attr, falsy/0 → removed *}
<span title={$count > 99 ? 'big' : null}>x</span>                   {* null → attribute removed entirely *}
<div class={[btn, active => $count > 1]}>x</div>                    {* array → conditional class list *}
<div data-info={[count => $count]}>x</div>                          {* data-* array → JSON-encoded *}
```

`n:attr` values are `Content::unwrap`-ed automatically, so an assoc `Content` spreads into attributes (NAttributeTest "spreads an associative array passed as a Content object"). Printing an ARRAY into a scalar attribute throws (`array is not allowed` — Latte 3.1 type check), so resolve iterable tag results before using them in attributes.

`s:trans` remains available when Statamic's fallback lookup is needed, including nested fallback expressions:

```latte
{s:trans key: "primary", fallback: (s:trans key: "fallback") /}
```

## Blocked Statamic tags and their native alternatives

`TagNode::$unsupportedTags` (src/Latte/Extensions/Nodes/TagNode.php) throws a `CompileException` at compile time for these — the message names the alternative:

| `{s:...}` tag | Use instead |
|---|---|
| `cache` | built-in `{cache}` |
| `foreach` | built-in `{foreach}` |
| `partial` | built-in `{include}` / `{embed}` |
| `switch` | built-in `{switch}` |
| `translate`, `trans_choice` | built-in `{_}` tag / `|translate` filter |
| `yield` | built-in `{yield}` |
| `section` | built-in `{section}` |
| `scope` | not supported in Latte |
| `loop` | built-in `{for}` / `{foreach}` |
| `increment` | variable assignment inside a loop |
| `dump` | built-in `{dump}` |

## Invariants (never do X because Y)

- Never document or rely on `{if $related}` being "always truthy for Deferred" as a bug — it is correct BECAUSE empties are never deferred. Changing the deferral predicate breaks template truthiness site-wide.
- Never tell users to count relationships via raw IDs; `count()`/`|length` intentionally reflect published, existing entries only.
- `$content->someMethod()` forwards to the source object (wrapped return, destructive methods blocked via `Content::GUARDED_METHODS`) — but prefer field access for blueprint data; method passthrough is for custom entry-class logic.
- Never write a single non-closed `{s:tag args}` in examples — it will not compile; self-close it.
- Never put a bare `|filter` inside `(s:...)` params — compile error by design; parenthesize.
- Never present `form:errors` as a loop of error strings — it's a boolean gate; errors iterate from the `form:create` capture.
- Never mutate view data in templates — array-style writes on `Content`/`Deferred` throw `LogicException`; property writes silently create a shadowing dynamic property instead of failing.

## Pitfalls: symptom → explanation

- **"`{if $entry->related}` shows the else branch but there ARE related entries"** → either the related entries are all unpublished/deleted (augmentation drops them, leaving an empty falsy list), or the field handle is misspelled — `Content::__get` returns `null` for unknown keys, silently.
- **"foreach over an entry prints all its fields"** → you looped a single `Content` (e.g. a `max_items: 1` relationship or a keyed tag result). Single things are objects; only LISTS are arrays. Access fields directly instead.
- **"`{$related}` prints nothing"** → echoing a relationship is a no-op by design (`Deferred::__toString` returns `''` for non-scalars). Loop it or access a field.
- **"`{ifset $related}` is true even though the field is empty"** → `ifset` checks existence, and the result is shape-dependent: empty multi-item fields wrap to a set-but-falsy `[]` (ifset true), empty `max_items: 1` fields to `null` (ifset false). Use `{if $related}`.
- **"`|length` shows 2 but the CMS lists 3 entries"** → the third is a draft; counts follow the materialized (published) set, matching what `{foreach}` renders.
- **"`resolve($a)` returned `$b`'s value"?** No — `resolve($a, $b)` returns the first NON-NULL: it's a coalesce. The filter form `|resolve:key` drills instead. Pick the right one.
- **"my bard/markdown field prints literal `<p>` tags as text"** → Latte context-aware auto-escaping applies to every printed value (the README's headline contract); HTML-bearing fields need `{$entry->content|noescape}`. Stock Latte behavior, not a Content-wrapping effect.
- **"my modifier behaves differently than in Antlers"** → modifiers get an empty context (`[]`) in Latte, deliberately; and if a Latte filter with the same name exists, it shadows the modifier entirely.
- **"`{title}` throws a syntax error"** → no key hoisting; write `{$value->title}` (in tag pairs) or `{$entry->title}`.
- **"literal `(s:foo bar)` prose got mangled"** → `(s:` followed by a letter is reserved syntax everywhere in a template and gets rewritten when it parses as a tag call; only `{* comments *}` and `{antlers}` islands are protected. Rephrase the prose (`(s::...)` lookalikes are safe).
- **"my `{s:mytag}` block says Unexpected tag but `(s:mytag)` works"** → block tags need registration at boot; the expression form resolves at runtime.

## How to verify a template-contract change

- Full suite: `composer test` (Pest; ~378 tests, ~19s). Lint/analyse: `composer lint`, `composer analyse`.
- Targeted: `./vendor/bin/pest tests/Feature/DeferredTest.php` (truthiness/count/json contract), `tests/Feature/ContentWrapTest.php` (shapes, `->`/`[]`), `tests/Feature/TagTest.php` (tag spellings, params, pagination), `tests/Feature/HelperTest.php` (`s()` function form), `tests/Feature/ModifierTest.php`, `tests/Feature/ResolverTest.php`, `tests/Tags/FormTest.php`, `tests/Tags/UserFormsTest.php`, `tests/Feature/SmartAttributesTest.php`, `tests/Feature/NAttributeTest.php`, `tests/Tags/UnsupportedTagsTest.php`, `tests/Feature/LayoutTest.php`.
- Quick repro harness: `$this->latte('<template>', ['var' => $data])` from tests/Concerns/InteractsWithLatteViews.php renders through the FULL pipeline (loader rewrites included) and returns a `TestView` for `assertSee`. Simulate the cascade's deferred relationship values with `$entry->augmentedValue('related_pages')` (the DeferredTest pattern). Put repro tests under tests/Feature/ or tests/Tags/ — tests/Pest.php binds the TestCase (and the `latte()` helper) only for those directories. Relationship fixtures: slug `testable-child` has `related_pages` populated, slug `testable` has it empty (see `childPage()`/`plainPage()` at the top of DeferredTest).
- Any user-visible behavior change here MUST be reflected in README.md and CHANGELOG.md — README is the contract this skill distills.

## Related skills

- **tag-bridge** — how the three tag spellings are implemented (loader rewrites, TagNode, runtime fetch).
- **data-layer** — Content/Deferred/Resolver internals and the wrap/unwrap boundaries.
- **extensions-and-nodes** — layout resolution, sections/yield, slot alias, antlers node internals.
- **caching** — `{cache}`/`{nocache}` semantics and their documented limitations.
- **testing** — the `$this->latte()` helper, fixtures, per-tag compat suite.
- **debugging** — inspecting compiled templates when a construct miscompiles.
- **orientation**, **quality-gates** — repo map and gate commands.
