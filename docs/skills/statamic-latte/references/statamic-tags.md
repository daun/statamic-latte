# Statamic Tags from Latte: the `s:` Bridge

Complete reference for calling [Statamic tags](https://statamic.dev/tags) from `.latte` templates.

## Contents
- Three spellings
- Argument syntax
- Body scope and result dispatch
- `as:` — capture the raw result
- `content:` — feed content-transforming tags
- Pagination
- Forms
- Blocked tags (use native Latte instead)
- Per-tag notes and runtime incompatibilities
- Reserved syntax warning

## Three spellings

**1. Tag form** — for rendering. Pair or self-closing; Statamic-style arguments:

```latte
{s:collection from: pages}...{/s:collection}
{s:link to: "snacks" /}                          {* self-closing prints the scalar result *}
{s:collection:count in: pages /}                 {* tag METHOD via second colon *}
{s:collection:pages sort: title}                 {* wildcard methods work too *}
    {$value->title}
{/s:collection:pages}                            {* full or base name both close it: {/s:collection} works too *}
```

- A single **non-closed** `{s:link to: "x"}` does not compile (Latte can't register a name as both single and paired). Self-close or pair — no exceptions.
- An empty pair prints the result like a self-closing tag: `{s:link to: "x"}{/s:link}` → `/snacks`.
- An unknown method on a tag with fixed methods throws at runtime: `{s:users:count /}` → "'count' is not a valid method of the users tag".
- Only tags registered when the template **compiles** work in this form; after installing an addon, `php artisan view:clear`. The other two spellings resolve at runtime.

**2. Expression form `(s:...)`** — anywhere Latte accepts an expression:

```latte
{var $entries = (s:collection from: pages, sort: title)}
{if (s:collection:count in: pages) > 1}many{/if}
{(s:link to: "snacks")|upper}
<a href="{(s:link to: 'snacks')}">Snacks</a>
<a href="https://example.com{(s:link to: 'snacks')}">absolute</a>
<li n:foreach="(s:collection from: pages) as $entry">{$entry->title}</li>
<p n:if="(s:collection:count in: pages) > 1">many</p>
<a n:attr="href: (s:link to: 'snacks'), class: 'btn'">Snacks</a>
<input type="checkbox" disabled={(s:collection:count in: pages)}>   {* smart attributes work *}
<div class={[btn, active => (s:collection:count in: pages) > 1]}>
<span title={(s:link to: 'snacks')?|upper}>                          {* nullsafe filter pipe OK *}
```

**3. Function form `s(...)` / `statamic(...)`** — a plain Latte function with Latte-native arguments; nested keys become quoted array keys:

```latte
{s(link, to: "fanny-packs")}
{foreach s('collection', ['from' => 'pages', 'title:contains' => 'Layout']) as $entry}...{/foreach}
{var $entries = s('collection', ['from' => 'pages', 'paginate' => 1])}
{if s('user:can', ['permission' => 'access cp'])}YES{/if}
{if s('session:has', ['key' => 'ghost'])}...{/if}
```

## Argument syntax

Values are ordinary Latte expressions (literals, variables, concatenation, ternaries). Statamic's nested parameter keys work with `:` or `=>` separators. **The last colon of a bareword chain is the key/value separator; earlier colons stay in the key:**

| Written | Parsed as |
|---|---|
| `from: pages` / `from:pages` / `from:"pages"` / `from => pages` | `['from' => 'pages']` |
| `status:is => draft` | `['status:is' => 'draft']` |
| `title:contains:Christmas` / `title:contains: Christmas` / `title:contains:"Christmas"` | `['title:contains' => 'Christmas']` |
| `title:contains:$var` / `title:contains: $a . $b` | expression value under key `title:contains` |
| positional bareword `here` | `['here']` |

Restrictions:

- **Bare filter pipes in values are a compile error** ("Bare filters are not supported"): `in: $c|lower` fails. Parenthesize: `in: ($c|lower)`. `($a || $b)` as a value is fine — only filters are affected.
- `as`, `content`, and `__sl_tag` are consumed by the bridge and never reach the Statamic tag (a *dynamic* `as: $var` IS forwarded — see below).

## Body scope and result dispatch

What a pair body sees depends on the tag's return value:

- **Iterable result** (list of entries, etc.): the body loops via a genuine Latte foreach — the item is **`$value`**, and `$iterator`, `{sep}`, `{first}`, `{last}` all work. No key variable, no Antlers-style field hoisting:

```latte
{s:collection from: pages, sort: title}
    {$value->title}{sep}, {/sep}
{/s:collection}
```

- **Single object / scalar result**: the body renders once with `$value` = result — but only when the result is not `null`/`''`/`false`; falsy results **skip the body entirely** (matches Antlers gating): `{s:user:profile}{$value->email}{/s:user:profile}` prints nothing for guests.
- A single keyed result (e.g. one entry) is a `Content` object and renders the body **once** — it is never iterated over its fields.
- Self-closing / empty pair: the stringified result is printed; booleans and null print `''` (a bool gates the pair, it never prints `1`); non-stringable objects print `''` rather than erroring.
- Whitespace inside pair bodies is preserved.

## `as:` — capture the raw result

```latte
{s:collection as: entries, from: pages, sort: title}
    {foreach $entries as $entry}{$entry->title}{sep}, {/sep}{/foreach}
{/s:collection}
```

- Binds the **raw** result (including a live paginator) to the variable; the body renders exactly **once** — you loop it yourself. Position among params is free.
- The variable is strictly body-scoped (restored/unset after the closing tag).
- The alias must be a **literal** name; `as: $dynamic` is forwarded to the Statamic tag as a normal param (some tags define their own `as`). An invalid alias name is a compile error.
- With a null result the body still renders once, with the variable = null.

## `content:` — feed content-transforming tags

Tags that transform their tag-pair body in Antlers (`widont`, `obfuscate`, `mjml`) take their input via `content:` on a self-closing tag; build multi-line input with `{capture}`:

```latte
{s:widont content: $entry->headline /}
{s:widont content: "Hello world" /}
{capture $text}...markup...{/capture}
{s:widont content: $text /}
```

## Pagination

Paginated tags return a **live Laravel paginator** (items wrapped like all view data). Loop it directly; meta comes from paginator methods, not separate variables:

```latte
{s:collection:pages as: entries, paginate: 10}
    {foreach $entries as $entry}{$entry->title}{/foreach}
    Page {$entries->currentPage()} of {$entries->lastPage()}, {$entries->total()} total
{/s:collection:pages}
```

Works identically via `{var $entries = (s:collection from: pages, paginate: 10)}` and `s('collection', [...])`. A plain pair (no `as:`) with `paginate:` loops just the current page's items as `$value`.

## Forms

`form:create` returns the form's **data, not rendered `<form>` HTML** — you build the markup and loop the fields:

```latte
{s:form:create as: form, in: contact}
    <form method="{$form->attrs->method}" action="{$form->attrs->action}">
        {foreach $form->fields as $field}
            <label>{$field->display}</label>
            <input type="text" name="{$field->name}" value="{$field->value}">
            {if $field->error}<span class="error">{$field->error}</span>{/if}
        {/foreach}
        <button type="submit">Send</button>
    </form>
{/s:form:create}
```

Available on the capture: `$form->attrs` (`method`, `action`), `$form->fields` (each with `handle`, `name`, `display`, `value`, `error`), `$form->honeypot`, `$form->errors` (array of message strings), `$form->error->{handle}` (first error per field).

- `form:success` — scalar gate + message: `{s:form:success in: contact}<p>{$value}</p>{/s:form:success}`.
- `form:errors` — **boolean gate, not an iterator**: the body renders once when errors exist (`$value` is `true`). List individual errors from the `form:create` capture (`{foreach $form->errors as $e}`).
- `form:submission` / `form:submissions` work as gate/iterator after submission.
- **Broken through the bridge**: `{s:form:fields}` throws; `{s:form:set}` outputs nothing — use `$form->fields` and per-tag `in:` params instead.
- `user:login_form`, `user:register_form`, `user:forgot_password_form` behave like `form:create` (data mode).

## Blocked tags (use native Latte instead)

These throw a `CompileException` naming the alternative:

| Blocked `{s:...}` | Use instead |
|---|---|
| `s:cache` | `{cache}` ([caching.md](caching.md)) |
| `s:foreach`, `s:loop` | `{foreach}` / `{for}` |
| `s:partial` | `{include}` / `{embed}` |
| `s:switch` | `{switch}` |
| `s:translate`, `s:trans`, `s:trans_choice` | `{_}` tag / `|translate` filter |
| `s:yield`, `s:section` | `{yield}` / `{section}` ([views-and-composition.md](views-and-composition.md)) |
| `s:scope` | not supported (Latte has real lexical scope) |
| `s:increment` | variable assignment |
| `s:dump` | `{dump}` |

## Per-tag notes and runtime incompatibilities

- **glide**: `{s:glide src: "assets::img/example.jpg", width: 100 /}` prints the URL. In a pair, `$value` **is the URL string**, not an object. `{s:glide:data_url src: ... /}` → base64 data URI. `glide:batch` does **not** work (body never reaches the tag) — capture instead: `{capture $src}{s:glide src: ... /}{/capture}<img src="{$src}">`.
- **svg**: `{s:svg src: "logo", class: "icon" /}` inlines raw SVG. In a pair body, `{$value}` gets HTML-escaped — always self-close.
- **vite / mix**: `{s:vite src: "resources/js/app.js" /}` emits script + preload tags; `{s:vite:content src: ... /}` inlines the file; missing entries throw.
- **nav**: `{s:nav handle: main}` — items have `title`, `url`, `depth`, `children`; recurse with `{foreach ($value->children ?? []) as $child}`. `nav:breadcrumbs` needs a current URL.
- **search**: the method is `results`, the param is `for:`: `{s:search:results for: "query"}{$value->title}...{/s:search:results}`.
- **collection**: drafts are excluded by default (`status:is => draft` includes them); `collection:next`/`collection:previous` throw without a current-entry context.
- **taxonomy / dictionary**: wildcard methods work (`{s:taxonomy:topics}`, `{s:dictionary:countries}`); unknown taxonomy handles throw.
- **users and gates**: `{s:user:can permission: "access cp"}`, `{s:user:is role: "editor"}`, `{s:user:in group: "editors"}` — boolean gates; `user_roles`/`user_groups` iterate with `->handle`/`->title`.
- **cookie / session**: side-effecting methods (`set`, `flash`, `forget`, `flush`) output nothing — self-close them; `{s:cookie:value key: "missing", default: "fallback" /}` supports defaults; test presence with `{if s('session:has', ['key' => 'x'])}`.
- **response tags**: `{s:404 /}` and `{s:redirect to: "/" /}` throw their HTTP exceptions as designed (aborting rendering); `{s:redirect /}` without a destination is a no-op.
- **locales / mount_url / parent**: return null/empty gracefully outside their context — pair bodies simply don't render.

## Reserved syntax warning

The source rewriter treats `(s:` **followed by a letter** as tag syntax everywhere except inside `{* comments *}` and closed `{antlers}...{/antlers}` blocks. Literal prose like `(s:foo bar)` gets rewritten and mangled — rephrase it or move it into a comment/antlers island. Double-colon lookalikes (`(s::FOO)`) are safe.
