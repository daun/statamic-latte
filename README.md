# Statamic Latte

[![Latest Version on Packagist](https://img.shields.io/packagist/v/daun/statamic-latte.svg)](https://packagist.org/packages/daun/statamic-latte)
[![Test Status](https://img.shields.io/github/actions/workflow/status/daun/statamic-latte/ci.yml?label=tests)](https://github.com/daun/statamic-latte/actions/workflows/ci.yml)
[![Code Coverage](https://img.shields.io/codecov/c/github/daun/statamic-latte)](https://app.codecov.io/gh/daun/statamic-latte)
[![License](https://img.shields.io/github/license/daun/statamic-latte.svg)](https://github.com/daun/statamic-latte/blob/master/LICENSE)

Use the [Latte](https://latte.nette.org/en/) templating language on [Statamic](https://statamic.com/) sites.

## Features

- Render `.latte` views
- Use Statamic's built-in tags and modifiers
- Resolve the current layout from entry data
- Render Antlers inline where useful

## Why Latte?

Latte is simple, safe, and fast. Templates are compiled. Expressions are plain PHP.
Output values are escaped automatically, in every context. Latte adds concise inline
control structures and smart attributes for expressive templating.

- **PHP syntax, no new language**: Expressions and conditions are plain PHP, so there's
  little mental context-switching between template and application code.
- **Context-aware escaping**: Latte understands HTML and escapes differently inside text,
  attributes, JavaScript or URLs.
- **Concise & expressive**: Control flow lives directly on the html elements, clarifying
  intent and reducing nesting.
- **Smart html attributes**: Booleans, `null`, arrays and data attributes render correctly
  with no manual string juggling, similar to modern frontend frameworks.
- **Fast**: Templates compile to PHP once and run as native code on every request.

**Antlers**

```antlers
{{ if entries | count }}
  <p>
    {{ entries }}
        <span data-details="{{ details | to_json | entities }}">
            {{ title }}{{ if !last }}, {{ /if }}
        </span>
    {{ /entries }}
  </p>
{{ /if }}
```

**Latte**

```latte
<p n:ifcontent>
    <span n:foreach="$entries as $entry" data-details={$entry->details}>
        {$entry->title}{sep}, {sep}
    </span>
</p>
```

## Installation

```sh
composer require daun/statamic-latte
```

## Usage

Once installed, you can use Latte views in your frontend. Save or rename your views
using the extension `.latte` and reference them as usual. Antlers and Latte views can live
side-by-side as long as view names are unique.

### Tags

[Statamic Tags](https://statamic.dev/tags) can be used via the native `s:` tag.

```latte
Found {s:collection:count in:pages /} pages:
```

Unlike Antlers,
Latte does not hoist loop item keys into scope. Inside a loop, the item itself is exposed
as `$value`. Access fields explicitly with `$value->title` over a bare `{title}`.

```latte
{s:collection:pages}
  {$value->title}
{/s:collection:pages}
```

Assign tag output using parentheses:

```latte
{var $entries = (s:collection from: pages, order: title)}
{foreach $entries as $entry}{$entry->title}{/foreach}
```

Or capture output into a variable using the `as` param:

```latte
{s:collection from: pages, as: entries}
  {foreach $entries as $entry}{$entry->title}{/foreach}
{/s:collection}
```

Use self-closing tags to output simple scalar return values from tags:

```latte
{s:link to: "snacks"/}
```

#### Arguments

Nested parameters are supported, with either `=>` or `:` separators. They accept
variables, literals and expressions.

```latte
{var $entries = (s:collection from: pages, status:is => draft)}
{var $entries = (s:collection from: pages, title:contains:Christmas)}
{var $entries = (s:collection from: pages, title:contains:$request->title)}
```

#### Pagination

Paginated tags return a Laravel paginator. Loop it directly and fetch meta from
its built-in methods.

```latte
{s:collection:pages as: entries, paginate: 10}
  {foreach $entries as $entry}{$entry->title}{/foreach}
  Page {$entries->currentPage()} of {$entries->lastPage()}
{/s:collection:pages}
```

#### Subexpressions

Wrap a tag in parentheses to use it inline as a plain expression — in `{var}`, conditions,
filters, `foreach`, and Latte's `n:` attributes:

```latte
{var $entries = (s:collection from: pages, order: title)}
{if (s:collection:count in: pages) > 1}many{/if}
{(s:link to: "snacks")|upper}

<li n:foreach="(s:collection from: pages) as $entry">{$entry->title}</li>
<p n:if="(s:collection:count in: pages) > 1">many</p>
<a n:attr="href: (s:link to: 'snacks')">Snacks</a>
```

#### Tags consuming nested content as input

Some tags transform their tag-pair body instead of returning data (e.g. `widont`,
`obfuscate` ). Hand it to the tag via the `content:` argument.

```latte
{s:widont content: $entry->headline /}
```

### Modifiers

[Statamic Modifiers](https://statamic.dev/modifiers) can be used as filters in Latte:

```latte
<h1>{$title|upper|truncate:50}</h1>
```

### Resolving values

Most values are augmented and stringified automatically on print, so you rarely need to
unwrap them yourself:

```latte
{$title}
{$author->name}
```

As an escape hatch for the cases where you hold a raw `Value`/`LabeledValue` object (e.g. when
passing one into a function or comparison), the `resolve` and `r` helpers and filter return the
underlying value:

```latte
{resolve($author)} or {r($author)}
{$author|resolve}
```

### Mixing Latte and Antlers

If you ever need to combine Latte and Antlers code, you can use the `antlers` tag in your
Latte views to render Antlers code inline. This can be useful for complex built-in tags or quick
prototyping by copy-pasting examples from the docs.

```latte
Rendered in Latte: {$title}

{antlers}
    Rendered in Antlers: {{ title }}
{/antlers}
```

### Layout

Just like in Antlers templates, the correct layout file will be used based on the data available in
your entries and blueprints.

By default, it will look for `/resources/views/layout.latte`, but you can configure specific entries
and collections to use different layouts instead by setting `layout: other_layout` on the entry or
collection config file.

### Sections & Yields

Use the `section` and `yield` tags to define content in one place and output it in
another. They map directly to Antlers' identical tags.

```latte
{* layout *}

{yield breadcrumbs /}

{* template *}

{section breadcrumbs}
    <a href="{$entry->url}">{$entry->title}</a>
{/section}
```

Use the self-closing form `{yield 'name' /}` when there's no fallback. To provide
default content for when no section was defined, use the paired form:

```latte
{yield breadcrumbs}
    Homepage
{/yield}
```

Sections and yields share Statamic's underlying content store, so they interoperate
freely across Latte, Antlers and Blade templates: a section defined in an Antlers
partial can be yielded in a Latte layout, and vice versa.

### Caching

#### Cache

Use the `cache` tag to cache parts of a view.

```latte
{cache for: '10 minutes'}
    {foreach $stocks as $stock}
        {$stock->fetchPrice()}
    {/foreach}
{/cache}
```

#### Nocache

The `nocache` tag can be used to exempt part of a view from
[static caching](https://statamic.dev/static-caching).

```latte
{include 'partials.nav', handle: main}
 
{nocache} 
    {if $logged_in}
        Welcome back, {$current_user->name}
    {else}
        Hello, Guest!
    {/if}
{/nocache}
 
{block content}{/block}
```

#### Limitations

The `nocache` tag is only supported for application-level static caching. Full file-based caching
requires JavaScript for `nocache` to work, which isn't yet implemented in this addon. See
[Caching Strategies](https://statamic.dev/static-caching#caching-strategies) for details.

Nesting `cache` and `nocache` is also not yet supported. The following **will not work**:

```latte
{cache}
    this will be cached
    {nocache}
        this will remain dynamic
    {/nocache}
    this will also be cached
{/cache}
```

## License

[MIT](https://opensource.org/licenses/MIT)
