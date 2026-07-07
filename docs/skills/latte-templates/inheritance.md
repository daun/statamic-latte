# Blocks, Inheritance, and Template Composition

The three reuse mechanisms: **layout inheritance** (`{layout}`), **horizontal reuse** (`{import}`), **unit inheritance** (`{embed}`), plus `{include}` and `{define}`.

## Contents
- Choosing a mechanism
- {block} semantics
- {layout} / {extends}
- Printing blocks: {include blockname}
- {define} — parameterized blocks
- {import} — block libraries
- {embed} — skeleton components
- {include file} and `with blocks`
- Variable scoping cheat sheet
- Dynamic names and existence checks

## Choosing a mechanism

| Need | Use |
|---|---|
| Insert a partial (header.latte) | `{include 'file.latte'}` |
| Page fills a site-wide layout | `{layout}` + `{block}` |
| Share a block library across templates | `{import}` |
| Reusable fragment with parameters ("function") | `{define}` + `{include name, args}` |
| Component skeleton whose slots the caller fills | `{embed}` |

## {block} semantics

```latte
{block sidebar}<aside>default content</aside>{/block}
```

- In a standalone template, `{block}` defines AND prints in place; it only marks the region overridable by a child.
- Parent content is the fallback when a child doesn't override.
- Child overrides use the parent's placement — a `{block post}` inside the parent's `{foreach}` renders the child's body on each iteration.
- Blocks READ outer-scope variables; variables created/assigned inside a named block stay local:

```latte
{var $foo = 'foo'}
{block post}{do $foo = 'new'}{var $bar = 'bar'}{/block}
{$foo}                {* 'foo' *}
{$bar ?? 'undefined'} {* 'undefined' *}
```

- A block is registered even inside `{if false}` — put conditions *inside* the block.
- Filters: `<title>{block title|stripHtml|capitalize}...{/block}</title>`. Anonymous `{block|spaceless}...{/block}` exists purely to filter a chunk.
- n:attribute form: `<article n:block=post>...</article>`.
- The last top-level block may omit its closing tag; a named closer `{/block footer}` must match; two same-named static blocks in one file = compile error (use dynamic names).
- **Local blocks** `{block local helper}...{/block}` never participate in inheritance (like private methods); usable before their definition within the file; imported local blocks are NOT includable by the importer.

## {layout} / {extends}

```latte
{* layout.latte *}
<html>
<head><title>{block title}{/block}</title></head>
<body>{block content}{/block}{block footer}&copy; 2008{/block}</body>
</html>

{* page.latte *}
{layout 'layout.latte'}
{var $robots = noindex}          {* header code runs and propagates variables to the layout *}
{block title}My blog{/block}
{block content}<p>Welcome.</p>{/block}
```

- `{extends}` is an alias. Must appear in the header before any output; only `{var}`, `{templateType}`, `{import}`, comments may precede it.
- In a template with `{layout}`, only header code and block contents execute — loose markup between blocks is ignored.
- Dynamic: `{layout $standalone ? 'plain.latte' : 'layout.latte'}`. `{layout none}` disables an auto-lookup layout; `{layout auto}` restores it (auto-lookup requires the `coreParentFinder` provider, see [php-api.md](php-api.md)).
- Multilevel chains work (layout ← blog-layout ← post).
- In child templates blocks sit at top level or nested inside another block: `{block content}<h1>{block title}Hi{/block}</h1>{/block}`.
- `{include parent}` inside an overriding block renders the parent's version (supplement instead of replace). Args allowed; filters forbidden.

## Printing blocks: {include blockname}

```latte
<h1>{include title}</h1>
{include footer from 'main.latte'}      {* block from another file, no inheritance *}
{include footer, foo: bar, id: 123}     {* pass arguments *}
{include this, $item}                   {* recurse into the current block *}
<title>{include heading|stripHtml}</title>
```

**Name disambiguation** — a bare word is a block; anything with a dot (`menu.2`, `file.latte`) is a FILE:

```latte
{include block menu.2}     {* or {include #menu.2} — force block *}
{include file $expr}       {* force file *}
{include block $name}      {* dynamic block name requires the keyword *}
```

## {define} — parameterized blocks

```latte
{define input, $name, $value, $type = 'text'}
    <input type={$type} name={$name} value={$value}>
{/define}

<p>{include input, 'password', null, 'password'}</p>
<p>{include input, name: 'email'}</p>
```

- Rendered ONLY when `{include}`d (a `{define}` prints nothing by itself).
- Parameters are always optional (default null unless given); types allowed (`{define input, string $name}`); declared params MASK same-named outer variables.
- Recursive structures: `{include this, $subitems}` inside the define.
- Avoid mixing positional and named arguments in one `{include}` — resolution is order-dependent.

## {import} — block libraries

```latte
{import 'blocks.latte'}         {* first tag after {layout}; imports all {block}s and {define}s *}
{import 'blocks.latte', foo: 1} {* args become variables inside imported blocks *}
```

- The imported file's loose text is ignored; it must not use `{layout}` itself but may `{import}` further files.
- Collisions: first import wins between imports; the importing template's own blocks override everything; `{include parent}` in an overriding block reaches the imported version.
- `{import}` in a layout makes the blocks available to all child templates.

## {embed} — skeleton components

Combines include (pass vars) with inheritance (override the embedded file's blocks inline):

```latte
{* collapsible.latte *}
<section class="collapsible {$modifierClass}">
    <h4>{block title}{/block}</h4>
    <div>{block content}{/block}</div>
</section>

{* usage *}
{embed 'collapsible.latte', modifierClass: my-style}
    {block title}Hello{/block}
    {block content}<p>Lorem…</p>{/block}
{/embed}
```

- Blocks inside `{embed}` form an **isolated layer** — no collision with same-named outer blocks. From inside you can `{include}`: blocks defined in the embed, the embedded file's non-local blocks, the main template's **local** blocks, and imported blocks — NOT ordinary outer blocks.
- Only `{block}` / `{import}` belong directly inside `{embed}`. *(3.1-dev feature: loose content inside `{embed}` becomes the embedded file's `{block default}`; combining loose content with an explicit `{block default}` is a compile error.)*
- Embedded files see only globals + passed vars. Overriding blocks see the caller's variables, but the embed file's own `{var}`s (run before the block) win over caller values; explicitly passed embed args beat both.
- Embed a `{define}`d **block** instead of a file: `{embed collapsible, ...}` — then outer-layer blocks REMAIN accessible inside. Disambiguate dynamics: `{embed block $name}` / `{embed file $name}`.
- Embeds nest; an embedded file may itself `{extends}` a layout. Self-closing `{embed 'f.latte'/}` renders it with fallbacks.

## {include file} and `with blocks`

```latte
{include 'template.latte', foo: 'bar'}
{include 'template.latte' with blocks}
```

- Included templates see ONLY globals + explicitly passed parameters — never the caller's locals.
- Blocks inside an included file are shielded from the includer's inheritance by default; `with blocks` shares them (the included file's `{define}`s become available to the includer).
- `{sandbox 'untrusted.latte', a: 1}` behaves like include with a security policy applied ([php-api.md](php-api.md)).

## Variable scoping cheat sheet

| Construct | Sees caller/outer variables? |
|---|---|
| `{block}` rendered in place | reads outer vars; writes stay local |
| `{include blockname}` — block defined in the SAME file | yes (template-scope vars visible) |
| `{include blockname}` — block from another file / imported | no — globals + args only |
| `{define}` | no — globals + args only. Exception: param-less define with static name, included in its own file |
| `{include file}` / `{embed file}` / `{sandbox}` | no — globals + explicit params only |
| `{import 'x.latte', foo: 1}` | args available inside the imported blocks |
| Block override inside `{embed}` | caller vars, overridden by embed-file `{var}`s and embed args |

Global variables are those passed to every template by providers/engine (`$this->global`), not ordinary render params.

## Dynamic names and existence checks

```latte
{foreach [Peter, John, Mary] as $name}
    {block "hi-$name"}Hi, I am {$name}.{/block}    {* child can override just {block hi-John} *}
{/foreach}

{ifset footer} ... {/ifset}
{ifset footer, header} ... {/ifset}       {* AND *}
{ifset block $name} ... {/ifset}
{if hasBlock(header) || hasTemplate('extra.latte')} ... {/if}
```

Block name expressions must evaluate to strings at runtime. `{layout}`/`{import}`/`{include}` names can be arbitrary expressions: `{include $ajax ? 'ajax.latte' : 'full.latte'}`.
