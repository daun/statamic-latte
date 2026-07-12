# Statamic Latte

Use the [Latte](https://latte.nette.org/en/) templating language on [Statamic](https://statamic.com/) sites.

## Features

- Use Statamic's built-in tags and modifiers
- Resolve the current layout from entry data
- Render Antlers inline where useful
- Use `<x-component>` for Latte and Blade components

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
  <nav>
    {{ entries }}
        {{ if link }}
            <a href="{{ link }}">{{ title }}</a>
        {{ else }}
            {{ title }}
        {{ /if }}
    {{ /entries }}
  </nav>
{{ /if }}
```

**Latte**

```latte
<nav n:ifcontent n:inner-foreach={$entries as $entry}>
  <a n:tag-if={$entry->link} href={$entry->link}>
    {$entry->title}
  </a>
</nav>
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
{var $entries = (s:collection from: pages, sort: title)}
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
{var $entries = (s:collection from: pages, sort: title)}
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

### Forms

Through the proxy, `form:create` returns the form's *data* rather than rendered
markup, so you build the `<form>` in Latte and loop the fields yourself. Capture
it with `as:`:

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

Check submission state with the scalar `form:success` and the boolean
`form:errors` gate:

```latte
{s:form:success in: contact}<p>{$value}</p>{/s:form:success}

{s:form:errors in: contact}
    <p>Please fix the errors below.</p>
{/s:form:errors}
```

To list individual error messages, read them from the `form:create` capture
(`$form->errors`, or `$form->error->{handle}` for a field's first error) — the
`form:errors` pair is a boolean gate here, not an iterator.

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

### Embeds & Slots

Latte composes templates with [`{embed}`](https://latte.nette.org/en/template-inheritance#toc-horizontal-reuse)
and [`{block}`](https://latte.nette.org/en/template-inheritance): a partial defines
named, fillable regions with `{block}`, and the embedding template overrides them
inside `{embed}`.

For parity with the component/slot vocabulary used by Antlers and Blade, `{slot}` is
provided as an **exact alias for `{block}`**. It is a pure synonym — same parsing, same
rendering — so you can use slot terminology on both sides of an embed:

```latte
{* partials/figure.latte *}

<figure>
    <img src="{$src}" alt="{$alt}">
    <figcaption>{slot caption}Default caption{/slot}</figcaption>
</figure>
```

```latte
{* template *}

{embed file 'partials.figure', src: $image->url, alt: $image->alt}
    {slot caption}A custom caption{/slot}
{/embed}
```

Because `{slot}` is identical to `{block}`, the two are interchangeable everywhere
(including layouts and `{extends}`) and you can freely mix them. Omitting a slot in the
embed falls back to the default content defined in the partial.

The `n:slot` attribute is also available (mirroring `n:block`) and works on both sides:

```latte
{* partials/figure.latte *}

<figcaption n:ifcontent n:slot="caption">Default caption</figcaption>

{* template *}

{embed file 'partials.figure'}
    <figcaption n:slot="caption">A custom caption</figcaption>
{/embed}
```

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

### Deployment & warmup

Laravel's `view:cache` command only compiles `.blade.php` templates, so `.latte` templates
are left to compile lazily on the first request that needs them. In production that means
your first visitor after a deploy pays the compile cost — and, worse, any Latte compile
error only surfaces on that first real request instead of at deploy time.

Run `php artisan latte:warmup` (also available as `php please latte:warmup`) after your
usual `view:cache` step to compile every `.latte` view up front:

```bash
php artisan view:clear
php artisan view:cache
php artisan latte:warmup
```

It discovers every `.latte` file across all registered view paths and namespace hints
(including vendor-published or overridden views), compiles each one, and reports a summary.
Per-file compile errors are caught and listed with the failing file and message; the command
exits non-zero if any file failed, so a broken template can fail your deploy instead of a
visitor's request.

```bash
php artisan latte:warmup --clear   # also clear previously compiled Latte output first
```

If you'd rather not add a separate deploy step, set `warmup_on_view_cache` to `true` in
`config/statamic-latte.php` (default `false`) to run the same warmup automatically right
after `view:cache` finishes:

```php
// config/statamic-latte.php
'warmup_on_view_cache' => true,
```

This is opt-in because `view:cache` failing your deploy on a Latte error is a deliberate
behavior change — with it enabled, a Latte compile error after `view:cache` will make the
process exit non-zero, the same way a Blade compile error already does.

### Components

Latte templates support the `<x-component>` syntax. A single tag resolves at compile time to either
a Latte or a Blade component. In case of a conflict, the Latte template wins.

```latte
<x-badge label="New"/>
<x-alert message={$error}/>
<x-forms.button type="submit">Go</x-forms.button>
```

A **Latte component** is a `.latte` template under the `components/` view directory
(`<x-forms.button>` → `components/forms/button.latte`). Its tag is desugared to a native
`{embed}`, so the template receives the attributes as variables and renders slots as blocks.
Anything without a matching template falls back to a **Blade component** (class, anonymous or
vendor), rendered at runtime.

#### Attributes

Attributes can be static strings, dynamic PHP expressions, or bare booleans. For Latte
components they become variables in the template; for Blade components, any attributes not
declared as constructor params flow into the `$attributes` bag.

```latte
<x-button type="submit"/>
<x-button count={$n}/>
<x-button label={strtoupper($s)}/>
<x-button disabled/>
<x-greeting ...{$props}/>
```

#### Backing class (optional)

A Latte component may have a backing class extending `Daun\StatamicLatte\Components\Component`
for logic. Constructor parameters are filled from the tag's attributes, and `data()` is spread
into the template's variables. Without a class, a component is just its template (anonymous).

```php
use Daun\StatamicLatte\Components\Component;

class Alert extends Component
{
    public function __construct(
        public string $type = 'info',
    ) {}

    public function data(): array
    {
        return [...parent::data(), 'classes' => "alert alert-{$this->type}"];
    }
}
```

#### Slots

**Latte components** use named and default slots, which compile to `{embed}` blocks. Fill a named
slot with `<x-slot:name>` (or `<x-slot name="name">`); the remaining body fills the default slot.
A slot that is omitted falls back to the `{slot …}` content defined in the component template, and
slot content is evaluated in the caller's scope.

```latte
{* components/alert.latte *}
<div class="alert">
    <strong>{slot title}Notice{/slot}</strong>
    {slot default}{/slot}
</div>

{* usage *}
<x-alert>
    <x-slot:title>Heads up</x-slot:title>
    Something happened.
</x-alert>
```

**Blade components** accept a body as the default slot (`{{ $slot }}`), captured as a pre-rendered
string and echoed directly. Named slots work too: each `<x-slot:name>` becomes a Blade
`ComponentSlot` (`$name`, with `isEmpty()`/`isNotEmpty()` and its own `$name->attributes`).

```latte
<x-card>
    Hello <strong>World</strong>
</x-card>

<x-framed>
    <x-slot:title class="font-bold">Heads up</x-slot:title>
    Body content
</x-framed>
```

#### Control attributes

Latte's `n:` control attributes work on components:

```latte
<x-card n:if="$show">content</x-card>

<x-greeting n:foreach="$names as $name" name={$name}/>
```

## License

[MIT](https://opensource.org/licenses/MIT)
