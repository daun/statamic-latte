---
name: latte-templates
description: Reference for Latte, the PHP templating engine by Nette (.latte files) with context-aware escaping — tag syntax, n:attributes, filters, blocks and template inheritance, the expression language, and the Latte\Engine PHP API. Use when writing, reviewing, or debugging .latte templates, fixing Latte compile errors, choosing tags or filters, answering Latte/Nette templating questions, or integrating and configuring Latte from PHP. Covers upstream Latte only, not framework-specific layers built on top of it (Nette Application, Symfony bridges, Laravel integration, etc).
---

# Writing Latte Templates

Latte is a PHP templating engine (latte.nette.org); this skill covers Latte 3.1. Templates compile to plain PHP and are cached. Its defining feature is **context-aware escaping**: Latte parses the HTML and escapes every printed value according to where it appears (HTML text, attribute, `<script>`, CSS, URL...). Never escape manually — write `{$var}` anywhere and it is safe. Expressions inside tags are PHP (a large subset), not a new language.

This file covers what every template touch needs. Load depth by lookup need:

| Open when you need to... | Read |
|--------------------------|------|
| look up any tag or n:attribute — conditions, loops, `$iterator`, variables, capture, try, n:class/n:attr/n:tag, translation, misc | [references/tags.md](references/tags.md) |
| look up a filter or expression function — signature, args, aliases, requirements | [references/filters.md](references/filters.md) |
| compose templates — blocks, `{layout}`, `{include}`, `{import}`, `{embed}`, `{define}`, or debug variable visibility between them | [references/inheritance.md](references/inheritance.md) |
| check what PHP is allowed inside `{...}`, bare-string rules, filter syntax edge cases | [references/expressions.md](references/expressions.md) |
| understand escaping per context, attribute type semantics, URL sanitization, whitespace handling | [references/escaping.md](references/escaping.md) |
| set up `Latte\Engine`, add filters/functions/extensions, sandbox untrusted templates, lint, debug | [references/php-api.md](references/php-api.md) |

## Syntax fundamentals

One delimiter for everything: `{...}`. Control tags and printing use the same braces.

```latte
{* comment — never appears in output *}
{$name}                      {* print variable, auto-escaped *}
{$name|upper|truncate:30}    {* filters, chained left to right, args after colon *}
{=date('Y') - $birth}        {* print any expression; = needed only when it doesn't start with $ or a function call *}
{if $stock}In stock{else}Sold out{/if}
{foreach $items as $item}<li>{$item}</li>{/foreach}
{var $page = 1}              {* create variable *}
```

**When `{` is NOT a tag:** if followed by whitespace, `'`, `"`, `{`, or `}`, it stays literal text. So CSS `body { color: red }` and JS `function () { return 1; }` work untouched. Literal braces where a tag would form: `{l}` prints `{`, `{r}` prints `}`. Inside `<script>` avoid `{letter...`; if needed use `<script n:syntax="off">` or `{syntax off}...{/syntax}`.

**n:attributes** — any pair tag wrapping a single element can move into the element:

```latte
<ul n:if="$items">
    <li n:foreach="$items as $item">{$item|capitalize}</li>
</ul>
```

- Plain `n:foreach` applies to the whole element (tag + content).
- `n:inner-foreach` applies only to the content (element printed once).
- `n:tag-if` applies only to the open/close tags (content always printed).
- Value forms: `n:if="$cond"`, `n:if=$cond`, `n:if={str_contains($val, "foo")}`. The whole attribute value is ONE Latte expression — `{$x}` inside it is not a nested tag.
- Sibling chains: `n:if` → `n:elseif` → `n:else` on consecutive elements (also `n:else` after `n:foreach`, `n:try`, `n:ifcontent`, `n:ifchanged`).
- Processing order is fixed by the engine, not by attribute order: `n:foreach` always wraps `n:if`, so `<li n:if="$iterator->first" n:foreach="...">` works.

**Whitespace:** a control tag alone on a line is removed together with its line (indentation + newline). Printing tags (`{$var}`, `{=...}`) keep their surroundings.

## Printing and escaping — the rules that matter

- Escaping is automatic and contextual. **Never** add quotes around a tag in JavaScript — Latte JSON-encodes values including quotes:

```latte
<script>
    let name = {$name};           {* → let name = "Rock'n'Roll"; *}
    let config = {$arrayOrObject}; {* any structure → JSON *}
    alert('Hi ' + {$name});        {* OK *}
    alert('Hi {$name}');           {* COMPILE ERROR: tag inside JS quotes *}
</script>
```

- Unquoted attributes are fine — Latte adds quotes and escapes: `<img src={$file} alt={$alt}>`.
- Attribute values react to types: `null`/`false` **omit the attribute entirely**, `true` renders a bare attribute, arrays are smart (`class={[btn, active => $isActive]}`, `style={[color: red]}`, JSON in `data-*`). Boolean attributes (`checked`, `disabled`...) follow truthiness.
- URL attributes (`href`, `src`, `action`, `formaction`) are sanitized: `javascript:` URLs become `""`. Override with `|nocheck`; opt other attributes in with `|checkUrl`.
- `{$trusted|noescape}` disables escaping — XSS risk, only for trusted HTML. From PHP, wrap trusted HTML in `Latte\Runtime\Html` instead.
- Nullsafe filter pipe: `{$title?|upper}` — skips the filter chain and returns null when the value is null (pairs well with attribute omission).

See [references/escaping.md](references/escaping.md) for per-context details, `<script type=...>` variants, and attribute semantics.

## Essential tags

Conditions:

```latte
{if $cond} ... {elseif $other} ... {else} ... {/if}
{ifset $var} ... {/ifset}                    {* isset() check; also {ifset blockname} *}
{switch $transport}{case train}By train{case plane, ship}Other{default}?{/switch}  {* strict === *}
```

Loops — `{foreach}` provides `$iterator` and an `{else}` branch for empty collections:

```latte
{foreach $people as $id => $person}
    {first}<ul>{/first}
    <li class={$iterator->odd ? odd : even}>{$iterator->counter}. {$person->name}</li>
    {sep}, {/sep}                            {* rendered between items, not after last *}
    {last}</ul>{/last}
{else}
    <p>No people found.</p>
{/foreach}
```

`$iterator` carries `->counter` (1-based), `->first`, `->last`, `->odd`, `->even`, `->parent` and more; loop control is `{breakIf}`, `{continueIf}`, `{skipIf}`, `{exitIf}`; other loops are `{for}`, `{while}`, `{iterateWhile}` — all detailed in [references/tags.md](references/tags.md).

Variables:

```latte
{var $name = 'John', $age = 27}     {* create; optional informative type: {var string $s = ...} *}
{default $lang = 'en'}              {* only if not already defined (existing null survives) *}
{capture $var}<ul>...</ul>{/capture}  {* buffer output into $var as safe Html *}
{do $counter++}                     {* evaluate, print nothing *}
```

Structure (details in [references/inheritance.md](references/inheritance.md)):

```latte
{layout 'layout.latte'}             {* this template fills the layout's blocks *}
{block content} ... {/block}
{include 'partial.latte', item: $item}   {* included file sees ONLY passed params + globals *}
{include blockname}                 {* print a block; dotted names are treated as files — force with {include block name} *}
{define input, $name, $type = 'text'}<input name={$name} type={$type}>{/define}
{embed 'card.latte', class: dark}{block title}Hi{/block}{/embed}
```

Error tolerance:

```latte
{try}
    <ul>{foreach $api->fetch() as $t}<li>{$t}</li>{/foreach}</ul>
{else}
    <p>Unavailable.</p>
{/try}
```

An exception discards the whole `{try}` output and renders `{else}`. `{rollback}` discards it manually.

HTML helpers (n:attributes only): `n:class="$active ? active, list-item"`, `n:attr="title: $t, checked: $c"`, `n:tag="$headingLevel"`, `n:ifcontent` (drop element if content is empty/whitespace).

## Expressions — PHP with sugar

Inside tags you write PHP expressions. Key differences ([references/expressions.md](references/expressions.md) has the full list):

- **Bare words are strings**: `{include foo}`, `[a, b-c]` ≡ `['a', 'b-c']`. But ALL_CAPS words are constants, and keywords (`true`, `in`, `default`...) can't be bare strings. Global constants need `\`: `{if \PROJECT_ID === 1}`.
- **Short ternary**: `{$stock ? 'In stock'}` (missing else = nothing).
- **`in` operator**: `{if $item in $items}` ≡ strict `in_array()`.
- **Array keys with colon**: `[one: 'a', two: 'b']` ≡ `['one' => 'a', ...]`; also used for named tag arguments: `{include 'f.latte', foo: 1}`.
- **Filters in expressions need parentheses**: `{var $x = ($title|upper)}`, `{if ($users|length) > 10}`.
- No statements: no `if/foreach/return/echo`; use Latte tags. Comments inside tags: `/* ... */` only.
- Allowed: `match`, arrow functions, `?->`, `??`, named args, `new`, `clone`, `instanceof`, first-class callables. Closures may only contain a single `return expr;`.

## Top gotchas

1. `{include name}` with a **dot** in the name (`block.2`, `file.latte`) means a **file**; force a block with `{include block name}` or `{include #name}`.
2. Included templates, `{embed}`, and blocks printed from other files do **not** see the caller's variables — only globals and explicitly passed parameters. A block `{include}`d in the same file where it's defined does see them.
3. Variables assigned **inside** a `{block}`/`{define}` stay local to it; blocks read outer variables fine.
4. Content of `<script>` and `<style>` is raw text: HTML elements and n:attributes inside are **not processed** (classic trap: `<div n:foreach>` inside `<script type="text/html">` stays literal). Escape hatch: `{contentType html}` right after the opening `<script>` tag.
5. In HTML mode `<script>` and `<style>` must have explicit closing tags, and n:attribute-carrying elements must be correctly paired — compile errors otherwise.
6. A `{block}` is registered even inside `{if false}` — for conditional output, put the `{if}` *inside* the block.
7. `{default $x = 1}` keeps an existing `null` (definedness check, not truthiness).
8. `{parameters $a, int $b = 5}` drops all *undeclared* incoming parameters.
9. `{skipIf}`-skipping every iteration triggers `{foreach}`'s `{else}` branch.
10. n:attribute processing order is fixed (foreach outside if, block outermost) regardless of written order.
11. `|stripHtml` output must never be combined with `|noescape` (decoded entities → XSS).
12. `{layout}`/`{extends}` templates: only header code and `{block}` contents execute; loose code between blocks is ignored (but header `{var}`s run and propagate to the layout).
13. Deferred conditions exist: `{if} ...content... {/if isset($row)}` — content evaluated first, printed only if the closing condition holds.

## Using from PHP (minimum)

```php
$latte = new Latte\Engine;
$latte->setCacheDirectory('/path/to/cache');    // compiled-template cache (older name: setTempDirectory())
$latte->addFilter('shortify', fn(string $s) => mb_substr($s, 0, 10));
$latte->addFunction('isWeekend', fn(DateTimeInterface $d) => $d->format('N') >= 6);
$latte->render('template.latte', ['name' => 'John']);   // or renderToString()
```

Engine configuration, typed parameter classes, `{templateType}`, sandbox for untrusted templates, and the `latte-lint` CLI are covered in [references/php-api.md](references/php-api.md).
