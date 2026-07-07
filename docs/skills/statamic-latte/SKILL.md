---
name: statamic-latte
description: Authoring Latte templates (.latte files) in Statamic sites using the daun/statamic-latte addon. Covers calling Statamic tags from Latte ({s:...} tags, (s:...) expressions, the s() function), Statamic modifiers as Latte filters, entry data access (Content/Deferred wrappers), Blade-style dot view resolution, automatic layout resolution from entry data, {section}/{yield}, {slot}, inline {antlers}, and {cache}/{nocache} caching. Use when writing, reviewing, or debugging .latte templates in a Statamic project, or when Statamic tags, modifiers, or entry data are used from Latte.
---

# Writing Latte Templates in Statamic

The `daun/statamic-latte` addon (built on miko/laravel-latte) adds `.latte` views to Statamic 6 sites: save views with the `.latte` extension and Statamic renders them like Antlers templates, with Statamic tags, modifiers, entry data, layout resolution, and static caching wired in. Latte, Antlers, and Blade views coexist as long as names are unique.

**This skill covers only the Statamic/Laravel-specific deltas.** Standard Latte — tag syntax, n:attributes, filters, blocks and inheritance, the expression language, escaping — is assumed known (see the `latte-templates` skill or latte.nette.org).

This file covers what every template touch needs. Load depth by lookup need:

| Open when you need to... | Read |
|--------------------------|------|
| call a Statamic tag — the three `s:` spellings, argument syntax, `$value` scope, `as:`/`content:` params, pagination, forms, blocked tags, per-tag notes | [references/statamic-tags.md](references/statamic-tags.md) |
| understand what a template variable holds — `Content`/`Deferred` wrappers, resolve helpers, or debug a data symptom | [references/data.md](references/data.md) |
| resolve view names, layouts, or compose across engines — dot syntax, automatic layouts, `{section}`/`{yield}`, `{slot}`, `{antlers}` | [references/views-and-composition.md](references/views-and-composition.md) |
| cache output — `{cache}` fragment caching, `{nocache}` static-cache holes, the compiled-template cache | [references/caching.md](references/caching.md) |

## Views and layouts in one minute

View names use **Blade-style dot syntax** resolved through Laravel's view finder — `partials.figure` is `resources/views/partials/figure.latte` (`.latte` auto-appended). Relative paths (`./sibling`, `../partials/x`) also work inside templates.

```latte
{include file 'partials.figure', src: $image->url}
{embed file 'partials.card'}{block title}Hi{/block}{/embed}
```

Always add the `file` keyword when referencing views: a dot-less name like `'welcome'` would otherwise be parsed as a block name.

**Layouts are automatic**, like Antlers: a page template just defines `{block}`s — no `{layout}` tag — and the addon wraps it in `resources/views/layout.latte`, or whatever the entry/collection sets via its `layout:` key. See [views-and-composition.md](references/views-and-composition.md).

## Data in one minute

Every view variable is normalized at the render boundary:

| Source value | Template shape |
|---|---|
| Entry / Asset / Term / group field / assoc array | `Content` object — `$entry->title` and `$entry['title']` both work |
| List / query result / collection | plain PHP array — `{foreach}`-able, `count()`-able |
| Scalar | untouched |
| Non-empty relationship field (top level) | lazy `Deferred` proxy — truthy, loopable, countable |

Values augment lazily and stringify on print — `{$title}`, `{$author->name}` just work. **No method calls**: `$entry->title()` fails (`Content` has no `__call`). Unknown field handles return `null` silently. Test relationships with `{if $related}`, never `{ifset}`. Details and pitfalls: [data.md](references/data.md).

## Statamic tags in one minute

Three spellings, full reference in [statamic-tags.md](references/statamic-tags.md):

```latte
{* 1. Tag form — for rendering. Pair or self-closing; tag methods via a second colon *}
{s:collection:pages sort: title}
    {$value->title}{sep}, {/sep}
{/s:collection:pages}
{s:link to: "snacks" /}

{* 2. Expression form (s:...) — anywhere Latte wants an expression *}
{var $entries = (s:collection from: pages, sort: title)}
{if (s:collection:count in: pages) > 1}many{/if}
<li n:foreach="(s:collection from: pages) as $entry">{$entry->title}</li>

{* 3. Function form — plain Latte call, Latte-native args *}
{foreach s('collection', ['from' => 'pages', 'title:contains' => 'Christmas']) as $entry}...{/foreach}
```

Inside a tag pair the current item is **`$value`** — unlike Antlers, field keys are never hoisted into scope (`{title}` is a syntax error; write `{$value->title}`). Arguments accept Statamic's nested keys with `:` or `=>` separators: `status:is => draft`, `title:contains: $request->title`. Capture raw results (including paginators) with `as: entries`.

## Modifiers as filters

Every Statamic modifier is available as a Latte filter — chainable, usable in expressions:

```latte
<h1>{$title|upper|truncate:50}</h1>
{if ("ABC"|is_uppercase)}...{/if}
{$things|sentence_list}
```

Two deliberate deviations from Antlers:

- **Name collisions resolve to the Latte filter.** A Latte core filter (or one you register) with the same name shadows the Statamic modifier entirely — e.g. `|truncate` runs Latte's word-preserving truncate, not Statamic's.
- **Modifiers run with an empty context.** Modifiers that read cascade context behave differently than in Antlers, by design. Values and arguments are unwrapped to raw Statamic data before the modifier runs.

## Top gotchas

1. A bare single tag `{s:link to: "x"}` (no `/}`, no closing tag) does **not compile** — Latte can't register a tag as both single and paired. Always self-close scalar tags: `{s:link to: "x" /}`.
2. **No key hoisting**: inside tag pairs the item is `$value`; a bare `{title}` is a Latte syntax error, not a field lookup.
3. A tag pair whose result is `null`, `''`, or `false` **skips its body entirely** (Antlers-parity gate) — `{s:user:profile}...{/s:user:profile}` renders nothing for guests.
4. `{if $related}` is the correct emptiness test for relationships; `{ifset}` gives shape-dependent wrong answers ([data.md](references/data.md)).
5. `$entry->title()` fails — no method dispatch on wrapped data. View data is read-only; never assign to it.
6. `form:create` returns **data, not `<form>` HTML**; `form:errors` is a **boolean gate**, not an error iterator ([statamic-tags.md](references/statamic-tags.md)).
7. `(s:` followed by a letter is **reserved syntax everywhere** outside `{* comments *}` and `{antlers}` blocks — literal prose like `(s:foo bar)` gets rewritten and mangled.
8. A bare filter pipe inside `(s:...)` params is a compile error: `in: $c|lower` fails — parenthesize: `in: ($c|lower)`. Filters on the *result* go outside: `{(s:link to: "x")|upper}`.
9. Printing an **array** into a scalar attribute throws (`<span title={(s:nav:breadcrumbs)}>` — "array is not allowed"). Loop or resolve iterable results first.
10. Keep `{yield}` and `{nocache}` **outside** `{cache}` blocks, and note a `{cache}` block whose output is `''`/`'0'` never caches ([caching.md](references/caching.md)).
11. `{s:tag}` block form requires the tag to be registered when the template **compiles** — after adding an addon/tag, run `php artisan view:clear`. The `(s:...)` and `s()` forms resolve at runtime and are immune.
12. HTML-bearing fields (bard, markdown) print escaped like everything else in Latte — output them with `{$entry->content|noescape}`.
13. Looping a single `Content` object (`{foreach $entry as $k => $v}`) walks **all its fields** and forces full augmentation — only lists are arrays; single things are objects.
14. Several Statamic core tags are compile-time blocked in favor of native Latte: `s:partial` → `{include}`/`{embed}`, `s:foreach` → `{foreach}`, `s:switch` → `{switch}`, `s:yield`/`s:section` → `{yield}`/`{section}`, `s:cache` → `{cache}`, `s:dump` → `{dump}`, etc. The compile error names the alternative.
15. `{s:svg}` inside a pair body gets HTML-escaped — self-close it: `{s:svg src: "logo", class: "icon" /}`.

## Also available (from miko/laravel-latte)

Laravel-flavored tags ship alongside: `{csrf}`, `{method 'PUT'}`, `{asset}`/`n:src`, `{link}`/`n:href` (Laravel route/URL helpers — don't use `n:href` with an `s:link` result; interpolate or use `n:attr`), `{x ...}` Blade-style class components, Livewire tags, `{_}`/`|translate`, `|nl2br`. Config lives in the publishable `config/latte.php` (compiled path, `auto_refresh` — tied to `app.debug`, so **production deploys must run `view:clear`**, `strict_parsing`, `scoped_loop_variables` off by default).
