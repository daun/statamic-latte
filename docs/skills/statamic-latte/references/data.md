# Data in Statamic Latte Templates

What variables actually hold, how Statamic values behave in Latte, and the pitfalls.

## Contents
- The three shapes
- Content objects: access rules
- Relationship fields and Deferred
- resolve() / r() / |resolve escape hatches
- Escaping and HTML fields
- Attributes and wrapped data
- Symptom → explanation

## The three shapes

Every view variable (and every `{s:...}` tag result) is normalized at the render boundary:

| Source value | Template shape |
|---|---|
| Entry / Asset / Term / group field / assoc array | **`Content` object** — `->key` and `['key']` both work |
| Sequential list / query result / collection | **plain PHP array** — `{foreach}`-able, `count()`-able |
| Scalar / unknown object | untouched |
| Raw `Value` / `LabeledValue` / query builder | peeled to the underlying value |

Rule of thumb: **lists are arrays, single things are objects.** A `max_items: 1` relationship or a keyed tag result is a `Content`, not a one-element array.

## Content objects: access rules

- `$entry->title` ≡ `$entry['title']`. Nested access chains lazily: `$entry->author->name`, `$page->meta->author`, grid rows as arrays of Content (`$page->blocks[0]->heading`).
- Fields **augment lazily per key** (only what you touch) and **stringify automatically on print** — `{$title}`, `{$author->name}` need no unwrapping.
- **Method passthrough**: `$entry->slug()` forwards to the source object and wraps the return — use it for custom entry-class methods (`$page->events()`). Destructive methods (save/delete/set/...) throw `LogicException`. For blueprint data, prefer field access.
- Unknown keys return `null` **silently** — a misspelled handle produces empty output, not an error.
- **Read-only**: array-style writes throw `LogicException`; property writes silently create a shadowing dynamic property. Treat both as forbidden — use `{var}` for template-local state.
- Iterating a single `Content` (`{foreach $entry as $k => $v}`) walks **all fields** and forces full augmentation — legal but expensive and almost never intended.

## Relationship fields and Deferred

Top-level view variables holding **non-empty** relationship fields (entries, terms, assets, users) are lazy `Deferred` proxies — the query runs at first touch. The contract:

- **`{if $related}` is correct in both directions**: `Deferred` is always truthy, and only non-empty relationships are deferred — empty ones stay eager and falsy (`[]`/`null`).
- **Never use `{ifset $related}`**: the answer is shape-dependent (empty multi-item field → `[]`, which IS set; empty `max_items: 1` field → `null`, which is NOT set).
- `count($related)` / `{$related|length}` count the **published, augmented** set — this can be less than the stored IDs (drafts and deleted entries are dropped) and always equals what `{foreach}` yields.
- `{foreach $related as $r}` yields `Content` items; a `max_items: 1` field materializes to a single `Content` (`{$author->title}` directly).
- Echoing a relationship (`{$related}`) prints `''` — never an error, never a supported pattern. `json_encode($related)` emits real entry data.
- `Deferred` appears **only** for top-level view variables; tag results and nested fields never contain one.

## resolve() / r() / |resolve escape hatches

Rarely needed — printing and property access already resolve automatically. For the cases where you hold a raw wrapper (passing a value into a PHP function, comparing):

- **Function** `resolve($a, $b, ...)` / alias `r(...)`: peels Statamic wrappers (`Value`, `LabeledValue`, query builders...) and returns the **first non-null** argument — it doubles as a coalesce. It does **not** peel `Content`.
- **Filter** `{$val|resolve:'author','name'}`: peels, then **drills** through keys (dot paths like `'author.name'` work); missing keys yield null.
- Same name, different behavior: the *function* coalesces, the *filter* drills.

## Escaping and HTML fields

Latte's context-aware auto-escaping applies to every printed value, including augmented Statamic data. HTML-bearing fields (bard, markdown) therefore print as visible tags unless you opt out:

```latte
{$entry->content|noescape}
```

That is stock Latte behavior, not a wrapper effect — everything in the base skill's escaping rules applies unchanged.

## Attributes and wrapped data

Latte 3.1 smart attributes work with wrapped data and `(s:...)` subexpressions (truthy → bare boolean attribute, `null` → attribute removed, arrays for `class`/`style`, JSON for `data-*`). Addon-specific delta: `n:attr` values are unwrapped automatically, so an **associative `Content` object spreads into attributes**:

```latte
<div n:attr="$attrs">   {* ['class' => 'big', 'data-id' => 7, 'hidden' => true, 'title' => null]
                           → class="big" data-id="7" hidden *}
```

Printing an **array** into a scalar attribute throws ("array is not allowed") — loop or pick a field from iterable tag results before using them in attributes.

## Symptom → explanation

| Symptom | Cause |
|---|---|
| `{if $entry->related}` hits `{else}` but the CMS shows related entries | all related entries are unpublished/deleted (dropped by augmentation), or the handle is misspelled (unknown keys are silently null) |
| `foreach` over an entry prints all its fields | you looped a single `Content` (e.g. `max_items: 1` field). Single things are objects — access fields directly |
| `{$related}` prints nothing | echoing a relationship is a no-op by design; loop it or access a field |
| `{ifset $related}` true though the field is empty | `ifset` is shape-dependent; use `{if $related}` |
| `\|length` shows 2 but the CMS lists 3 | the third is a draft; counts follow the published set, matching `{foreach}` |
| `resolve($a, $b)` returned `$b`'s value | that's the coalesce: first **non-null** wins. To drill into keys use the `\|resolve` filter |
| bard/markdown prints literal `<p>` as text | auto-escaping; add `\|noescape` |
| modifier behaves differently than Antlers | modifiers get an empty context; and a same-named Latte filter shadows the modifier entirely |
| `{title}` throws a syntax error | no key hoisting — write `{$value->title}` / `{$entry->title}` |
| `$entry->delete()` throws | destructive methods blocked — wrappers are read-only |
