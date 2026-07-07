# Latte Tag Reference

Complete author-facing reference for Latte 3.x tags and n:attributes. Blocks/inheritance tags (`{block}`, `{define}`, `{include}`, `{layout}`, `{import}`, `{embed}`) are summarized here but detailed in [inheritance.md](inheritance.md).

## Contents
- Printing
- Conditions
- Loops and $iterator
- Variables, capture, types
- Includes and structure (summary)
- Exception handling: try / rollback
- HTML helper n:attributes (n:class, n:attr, n:tag, n:ifcontent)
- n:attribute mechanics (prefixes, order, n:else)
- Translation
- Misc (contentType, syntax, spaceless, debug)

## Printing

| Tag | Meaning |
|---|---|
| `{$var}`, `{$obj->prop}`, `{expr}` | print escaped expression |
| `{=expr}` | print expression that doesn't start with `$`/function call: `{='0' . $n}` |
| `{$var\|filter:arg1, arg2}` | print with filters |
| `{l}` / `{r}` | literal `{` / `}` |
| `{_'text'}` | print translated text (needs translator) |

Self-closing slash on a print tag (`{time() /}`) is a compile error.

## Conditions

```latte
{if $cond} ... {elseif $c2} ... {else} ... {/if}
```
n:attribute forms: `n:if`, `n:inner-if`, `n:tag-if`; sibling chain `n:if` → `n:elseif` → `n:else`.

**Deferred condition** — condition in the closing tag; the content is rendered to a buffer first, printed only if the condition is true afterwards. `{else}` allowed, `{elseif}` not:

```latte
{if}
    <h1>Rows</h1>
    {foreach $rs as $row}...{/foreach}
{/if isset($row)}
```

`{ifset $var}` / `{elseifset}` — `isset()` test. Multiple = AND: `{ifset $a, $b}`. Bare or `#`-prefixed names test **blocks**: `{ifset footer}`, `{ifset block $name}`. n:attr: `n:ifset`.

`{ifchanged}` — renders content when the watched value changed since the previous loop iteration. With args watches those expressions, without args watches its own rendered content. Supports `{else}`. n:attr: `n:ifchanged`.

```latte
{foreach ($names|sort) as $name}
    <h2 n:ifchanged>{$name[0]}</h2>
    <p>{$name}</p>
{/foreach}
```

`{switch}` — strict `===`, no fallthrough, `{case}` accepts multiple comma-separated values (spread `...$arr` allowed), one `{default}` max:

```latte
{switch $status}
{case $status::Sold, $status::Unknown}<i>not available</i>
{default}in stock
{/switch}
```

## Loops and $iterator

```latte
{foreach $items as $key => $value} ... {else} rendered when empty {/foreach}
```
n:attr: `n:foreach`, `n:inner-foreach`. Destructuring works: `as [$a, $b]`, `as list($a, , $c)`; by-reference `as &$v`.

`$iterator` (a CachingIterator) is defined inside the loop, restored/null outside:

- `->counter` (1-based), `->counter0`, `->first`, `->last` (works for generators of unknown length), `->odd`, `->even`, `->empty`
- `->nextValue`, `->nextKey` — look-ahead
- `->parent` — the surrounding loop's iterator (null across `{include file}` boundaries)
- method forms with chunk width: `isFirst(2)`, `isLast(2)` = boundaries of groups of N

Loop shortcuts (tags and n:attrs `n:first`, `n:sep`, `n:last`), each supports an optional width arg and `{else}`:

```latte
{foreach $items as $item}
    {first}<ul>{/first}
    <li>{$item}</li>
    {sep}, {/sep}      {* separator: printed between items, not after the last *}
    {last}</ul>{/last}
{/foreach}
```

Loop control:

- `{breakIf $cond}` / `{continueIf $cond}` — break/continue.
- `{skipIf $cond}` — continue **without incrementing `$iterator->counter`** (no numbering gaps). If every iteration is skipped, `{foreach}`'s `{else}` branch renders.
- `{exitIf $cond}` — end rendering of the template or block early. Allowed at file/block top level, not inside foreach.
- Constraint: `breakIf`/`continueIf`/`skipIf` must sit directly in the loop body (or inside `{if}`/`{ifset}` there), not inside a `{block}` within the loop.

Other loops:

```latte
{for $i = 0; $i < 10; $i++} {$i} {/for}                {* n:for *}
{while $row = $res->fetch()} ... {/while}              {* n:while *}
{while} ... {/while $item = $item->getNext()}          {* do-while: condition at closing tag *}
```

`{iterateWhile}` — inner loop inside `{foreach}` consuming consecutive items while a condition holds (grouping linear data). Condition at the opening tag = pre-test, at the closing tag = post-test:

```latte
{foreach $items as $item}
    <ul>
        {iterateWhile}
        <li>{$item->name}</li>
        {/iterateWhile $item->groupId === $iterator->nextValue?->groupId}
    </ul>
{/foreach}
```

Loop variables leak after the loop by default; the engine feature `Feature::ScopedLoopVariables` scopes them (see [php-api.md](php-api.md)).

## Variables, capture, types

```latte
{var $a = 1, $b = 'x'}          {* create; trailing comma OK; {var $a, $b} = null-init *}
{var string $name = $article->getTitle()}   {* type is informative only *}
{var $obj->prop = 1}            {* property assignment allowed *}
{default $lang = 'en'}          {* assign only if NOT DEFINED — existing null survives (array_key_exists) *}
{do $counter++}                 {* evaluate expression, print nothing *}
{capture $var} ... {/capture}   {* buffer output into $var as Latte\Runtime\Html (no double-escape) *}
{capture $var|striphtml|upper}  {* filters on capture *}
```

- `{default}` on `$obj->prop` is a compile error; capture target must be writable (`$x->prop` OK, `$x->m()` not).
- n:attr: `n:capture="$var"`.

Type declarations (informative — for IDEs/static analysis; see [php-api.md](php-api.md)):

```latte
{templateType App\CatalogTemplateParameters}    {* header only; params come from class properties *}
{varType Nette\Security\User $user}             {* TYPE FIRST, then variable *}
{parameters $a, ?int $b, int|string $c = 10}    {* declares params; UNDECLARED incoming vars are dropped *}
{templatePrint}   {varPrint}   {varPrint all}   {* scaffold generators — render suggestions instead of the page *}
```

Accepted type syntax includes nullable `?Foo`, unions, intersections, generics `array<Foo<int>>`, array shapes `array{0: int}`.

## Includes and structure (summary — see [inheritance.md](inheritance.md))

```latte
{include 'file.latte'}                       {* file; sees only globals + passed params *}
{include 'file.latte', foo: 'bar', id: 123}
{include blockname}   {include block $name}  {* block; dotted bare names mean FILE — disambiguate! *}
{include footer from 'blocks.latte'}
{include this, $arg}    {include parent}     {* recursion / parent block content *}
{layout 'layout.latte'}  {* alias {extends} *}
{import 'blocks.latte'}                      {* pull in block/define library *}
{embed 'card.latte', var: 1}{block title}...{/block}{/embed}
{block name} ... {/block}    {define name, $p1, $p2 = 'x'} ... {/define}
{sandbox 'untrusted.latte', a: 1}            {* sandboxed include; explicit params only *}
{ifset blockname} ... {/ifset}               {* also hasBlock(name), hasTemplate(name) functions *}
```

## Exception handling: try / rollback

```latte
{try}
    <ul>{foreach $twitter->loadTweets() as $t}<li>{$t->text}</li>{/foreach}</ul>
{else}
    <p>Could not load tweets.</p>
{/try}
```

- Exception inside → entire `{try}` output discarded, `{else}` (if present) rendered. Rendering continues after the block.
- `{rollback}` — unconditionally discard the enclosing `{try}` output (jumps to `{else}` if present). Compile error outside `{try}`. Rollback also restores `$iterator` state and block definitions made inside.
- n:attr `n:try` (pairs with sibling `n:else`).
- Register an engine-level exception handler to log swallowed exceptions ([php-api.md](php-api.md)).

## HTML helper n:attributes

`n:class` — comma-separated conditional class list; empty result drops the attribute. Combining with a literal `class` attribute is a compile error:

```latte
<a n:class="$item->isActive() ? active, $iterator->first ? 'first main', list-item">
```
Since Latte 3.1 a plain `class={[...]}` attribute has the same powers (arrays, key => bool).

`n:attr` — arbitrary attributes from `name: expr` pairs (or an array literal / variable). `null` drops the attribute, booleans control presence, arrays work for class/style:

```latte
<input type="checkbox" n:attr="value: $item->getValue(), checked: $item->isActive()">
```

`n:tag` — replaces the element name at runtime (`null` = keep original; validated — cannot switch to/from void elements or to `script`/`style`):

```latte
<h1 n:tag="$level ? 'h' . $level" class="main">{$title}</h1>
```

`n:ifcontent` — omit the whole element when its rendered content is empty or whitespace-only (`'0'` counts as content). With `n:foreach` it applies per iteration. Compile error on void elements:

```latte
<div class="error" n:ifcontent>{$error}</div>
```

## n:attribute mechanics

- Value forms: `n:if="$cond"`, `n:if=$cond`, `n:if={expr with "quotes"}`. The value is a single Latte expression — `{...}` inside is not a nested tag.
- Prefixes: plain (whole element), `n:inner-` (content only), `n:tag-` (open+close tags only, content always printed). All three can coexist on one element. Nonsensical combos are compile errors: `n:inner-tag`, `n:inner-attr`, `n:inner-class`, `n:inner-ifcontent`, `n:inner-syntax`.
- **Processing order is fixed by the engine** regardless of written order: `n:block`/`n:define`/`n:embed` outermost, then `n:attr`/`n:class`/`n:tag`, `n:try`, `n:foreach`, `n:for`/`n:while`, `n:first`/`n:sep`/`n:last`, `n:if`/`n:ifset`, `n:ifchanged`, `n:ifcontent`. So `n:if` can safely use `$iterator` from an `n:foreach` on the same element.
- `n:else`/`n:elseif` element must **immediately** follow the conditional element (only whitespace between).
- Duplicate n:attribute on one element = compile error. n:attributes cannot be generated by `{tags}` (`<a {if}n:href{/if}>` is an error).
- Inside `<script>`/`<style>` bodies, n:attributes on markup are NOT processed (raw-text content — see [escaping.md](escaping.md)).
- Tag names are case-insensitive in HTML mode, case-sensitive in XML mode.

## Translation

Requires `TranslatorExtension` ([php-api.md](php-api.md)).

```latte
{_'Basket'}    {_$item}    {_'Basket', domain: order}
{translate}Order{/translate}    {translate domain: order}...{/translate}
<h1 n:translate>Order</h1>
{$item|translate}
```

With a locale-aware translator, static strings may be translated at compile time (per-language cache).

## Misc

- `{contentType}` — escaping mode for the template: `html` (default), `xml`, `javascript`, `css`, `calendar`, `text` (no escaping). A full MIME type (`{contentType application/xml}`) also sends the header. Allowed only in the template header — with one exception: `{contentType html}` directly after an opening `<script ...>` tag re-enables HTML parsing inside it.
- `{syntax double}` → only `{{...}}` are tags; `{syntax off}` → no tag parsing until `{/syntax}`. Element-scoped: `n:syntax="off"` / `"double"`.
- `{spaceless} ... {/spaceless}` / `n:spaceless` / `|spaceless` filter — collapses inter-tag whitespace (keeps `<pre>`, quoted attribute values, and single word-separating spaces). Forbidden inside `<script>`/`<style>` bodies and attribute values; `n:spaceless` on `<script>` leaves content verbatim.
- `{dump $var}` / `{dump}` (all vars) — Tracy Bar dump. `{debugbreak}` / `{debugbreak $i == 42}` — Xdebug breakpoint. `{trace}` — throws an exception whose stack trace is template-level.
- `{php ...}` — by default an obsolete alias of `{do}` (single expression). Arbitrary multi-statement PHP requires `RawPhpExtension`.
- Nette-framework-only tags you may encounter (not core Latte): `n:href`, `{link}`, `{plink}`, `{control}`, `{snippet}`, `{cache}`, `{form}`, `{input}`, `{label}`, `n:name`, `{asset}`.
