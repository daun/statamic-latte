# Views, Layouts, and Cross-Engine Composition

How `.latte` views are found, how layouts are applied, and the tags that bridge Statamic's composition vocabulary: `{section}`/`{yield}`, `{slot}`, `{antlers}`.

## Contents
- View name resolution
- Automatic layout resolution
- Page + layout pattern
- {section} / {yield}
- {slot} / n:slot
- {antlers} — inline Antlers

## View name resolution

Views resolve through **Laravel's view finder**, not Latte's file loader:

- **Dot syntax**: `partials.figure` → `resources/views/partials/figure.latte`. The `.latte` extension is auto-appended when the bare name doesn't exist. View **namespaces** (`package::view`) work.
- **Relative paths**: names starting with `/`, `./`, or `../` resolve relative to the referring template file: `{include file './nested/include'}`, `{include file '../welcome.latte'}`.
- **Use the `file` keyword** in `{include}`/`{embed}` when referencing views: a dot-less quoted name (`'welcome'`) would be parsed as a *block* name by Latte's disambiguation rule. Dotted names happen to parse as files anyway, but `file` makes it explicit:

```latte
{include file 'partials.nav', handle: main}
{embed file 'partials.figure', src: $image->url, alt: $image->alt}
    {slot caption}A custom caption{/slot}
{/embed}
```

- **Coexistence**: `.latte`, `.antlers.html`, and `.blade.php` views live side by side; names must be unique. When a bare name matches both, the `.latte` view wins (its extension is first in the finder list).

## Automatic layout resolution

The layout is chosen **from entry data, exactly like Antlers** — page templates don't write a `{layout}` tag:

- Default: `resources/views/layout.latte`.
- Per entry or collection: set `layout: other_layout` in the entry front-matter or collection config.
- Includes and embeds are exempt — partials are never wrapped in the page layout.
- Latte's native `{layout 'name'}` / `{layout none}` / `{extends}` still work and take precedence when written explicitly; auto-resolution only supplies a parent when the template doesn't set one.

## Page + layout pattern

The layout is a normal Latte parent template; pages just fill its blocks:

```latte
{* resources/views/layout.latte *}
<html>
<head><title>{block title}Untitled{/block}</title></head>
<body><main>{block content}{/block}</main></body>
</html>

{* resources/views/page.latte — no {layout} tag; wired via entry data *}
{block title}{$page->title}{/block}

{block content}
    <h1>{$page->title}</h1>
    <div>{$page->content|noescape}</div>
{/block}
```

All standard Latte inheritance rules apply (see the base skill's inheritance reference): loose markup between blocks in a page template is ignored, header `{var}`s propagate to the layout, `{import}`/`{define}`/`{embed}` work normally.

## {section} / {yield}

Define content in one place, output it in another — mapping to Antlers' identical tags:

```latte
{* layout *}
{yield breadcrumbs /}                 {* self-closing: no fallback *}
{yield breadcrumbs}Homepage{/yield}   {* paired: fallback when no section defined *}

{* template *}
{section breadcrumbs}
    <a href="{$entry->url}">{$entry->title}</a>
{/section}
```

- Names: bare word, quoted string, or a dynamic expression (`{section $name}`, `{yield $name /}`).
- **Order-independent**: a `{yield}` may appear before its `{section}` — placeholders resolve after the whole template renders.
- **Cross-engine**: sections share Statamic's content store, so a section defined in an Antlers or Blade partial can be yielded in a Latte layout and vice versa (`{{ section:msg }}` ↔ `{yield 'msg' /}`).
- Do **not** place `{yield}` inside `{cache}` — the placeholder token gets frozen in the cached fragment ([caching.md](caching.md)).

## {slot} / n:slot

`{slot}` is an **exact alias of Latte's `{block}`** (`n:slot` ≡ `n:block`, `n:inner-slot` ≡ `n:inner-block`) — pure vocabulary parity with the component/slot terminology of Antlers and Blade. Same parsing, same rendering, freely interchangeable with `{block}` everywhere, including layouts and `{extends}`:

```latte
{* partials/figure.latte *}
<figure>
    <img src="{$src}" alt="{$alt}">
    <figcaption n:ifcontent>{slot caption}Default caption{/slot}</figcaption>
</figure>

{* caller *}
{embed file 'partials.figure', src: $image->url, alt: $image->alt}
    {slot caption}A custom caption{/slot}
{/embed}

{* n:attribute form on either side *}
{embed file 'partials.figure'}
    <figcaption n:slot="caption">A custom caption</figcaption>
{/embed}
```

Omitting a slot in the embed falls back to the default content defined in the partial — standard `{embed}`/`{block}` semantics from the base skill apply (isolated block layer, variable scoping).

## {antlers} — inline Antlers

Render Antlers inline for complex built-in tags or to paste doc examples verbatim:

```latte
Rendered by Latte: {$title}
{antlers}
    Rendered by Antlers: {{ title }}
    {{ collection:pages }}{{ title }}{{ /collection:pages }}
{/antlers}
```

- **Scope**: all in-scope Latte variables (including `{var}` locals) are unwrapped and handed to Antlers, which does its own augmentation and key hoisting — deep nesting (`{{ page:meta:featured_page:title }}`) and relationships cross the boundary fine.
- Latte syntax inside the block is **not parsed** (`{$title}` prints literally), and Statamic-looking `(s:...)`/`{s:...}` text inside a **closed** `{antlers}...{/antlers}` block is protected from rewriting. An unclosed block loses that protection.
- Takes no arguments; `n:antlers` is not supported — both are compile errors.
- Sections defined inside the Antlers block interoperate with Latte `{yield}` and vice versa.
