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

## Installation

Run the following command from your project root:

```sh
composer require daun/statamic-latte
```

Alternatively, you can search for this addon in the `Tools > Addons` section of
the Statamic control panel and install it from there.

## Usage

Once installed, you're ready to use Latte views in your frontend. Just save or rename your views
using the extension `.latte` and reference them as usual. Of course, you can use both side-by-side
as long as you're making sure there's only ever one identically named view.

```diff
- /resources/views/project.antlers.html
+ /resources/views/project.latte
```

**Antlers**

```antlers
<ul>
    {{ songs }}
        <li>{{ value }} (Next: {{ next:value }})</li>
    {{ /songs }}
</ul>
```

**Latte**

```latte
<ul n:inner-foreach="$songs as $song">
    <li>{$song} (Next: {$iterator->nextValue})</li>
</ul>
```

## Tags

[Statamic Tags](https://statamic.dev/tags) can be used via the `s` helper function:

**Antlers**

```antlers
{{ collection:pages take="8" }}
  {{ title }}
{{ /collection }}
```

**Latte**

```latte
{foreach s('collection:pages', take: 8) as $entry}
  {$entry->title}
{/foreach}
```

## Modifiers

[Statamic Modifiers](https://statamic.dev/modifiers) can also be used as filters in Latte:

**Antlers**

```antlers
<h1>{{ title | upper | truncate(50) }}</h1>
```

**Latte**

```latte
<h1>{$title|upper|truncate:50}</h1>
```

## Mix & Match

If you ever need to combine Latte and Antlers code, you can use the `antlers` tag in your
Latte views to render Antlers code inline. This can be useful for complex built-in tags or quick
prototyping by copy-pasting examples from the docs.

```latte
Rendered in Latte: {$title}

{antlers}
    Rendered in Antlers: {{ title }}
{/antlers}
```

## Layout

Just like in Antlers templates, the correct layout file will be used based on the data available in
your entries and blueprints.

By default, it will look for `/resources/views/layout.latte`, but you can configure specific entries
and collections to use different layouts instead by setting `layout: other_layout` on the entry or
collection config file.

## Caching

### Cache

Use the `cache` tag to cache parts of a view.

```latte
{cache for: '10 minutes'}
    {foreach $stocks as $stock}
        {$stock->fetchPrice()}
    {/foreach}
{/cache}
```

### Nocache

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

### Limitations

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
