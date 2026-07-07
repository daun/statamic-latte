# Filters, functions, and providers

## Contents

- [Choosing between them](#choosing-between-them)
- [Registration](#registration)
- [Classic filters](#classic-filters)
- [Contextual filters (FilterInfo)](#contextual-filters-filterinfo)
- [Returning HTML safely](#returning-html-safely)
- [Functions](#functions)
- [Providers](#providers)
- [Runtime dispatch details](#runtime-dispatch-details)

## Choosing between them

| You're building | Use |
|---|---|
| a transformation of one value, piped: `{$x\|thing}` | **filter** |
| a calculation/lookup callable anywhere in expressions: `{if thing($x)}` | **function** |
| a runtime service that compiled tag code needs | **provider** |
| new language structure or markup generation | tag — see [custom-tags.md](custom-tags.md) |

Native PHP functions are callable in expressions by default (unless sandboxed) — don't register wrappers for them.

## Registration

```php
// direct — quick one-offs
$latte->addFilter('shortify', fn(string $s, int $len = 10) => mb_substr($s, 0, $len));
$latte->addFunction('isWeekend', fn(\DateTimeInterface $d) => $d->format('N') >= 6);
$latte->addProvider('myService', $service);

// via extension — the packaged way
class MyExtension extends Latte\Extension
{
    public function getFilters(): array   { return ['shortify' => $this->shortify(...)]; }
    public function getFunctions(): array { return ['isWeekend' => $this->isWeekend(...)]; }
    public function getProviders(): array { return ['myService' => $this->service]; }
}

// via attributes on the template-parameters object passed to render()
#[Latte\Attributes\TemplateFilter]   public function shortify(string $s): string { ... }
#[Latte\Attributes\TemplateFunction] public function initials(string $name): string { ... }
```

Names must match `[a-z]\w*` (case-insensitive). Last registration wins on name collisions between extensions. Filters/functions/providers are consumed **once, at `addExtension()` time** — registering an extension does not pick up later external changes; return closures that resolve lazily if the backing set is dynamic.

## Classic filters

The piped value is the first argument; `:`-separated template args follow, positional or named. Type hints, defaults, and variadics behave as in plain PHP:

```php
$latte->addFilter('truncate', fn(string $s, int $len = 50, string $append = '…') => ...);
// {$title|truncate:30}   {$title|truncate, append: '->'}
```

Filter names are also usable in `{block name|filter}` and `(expr|filter)` expressions. Dynamic lazy registration exists via `$latte->addFilterLoader(fn(string $name): ?callable => ...)` but is **deprecated** — prefer explicit maps.

## Contextual filters (FilterInfo)

A filter whose **first parameter is typed `Latte\Runtime\FilterInfo`** becomes content-type-aware; the piped value moves to the second parameter. Latte detects this by reflection — the type hint alone opts you in.

```php
use Latte\{ContentType, Runtime\FilterInfo};

$latte->addFilter('money', function (FilterInfo $info, float $amount): string {
    $info->validate([null, ContentType::Text], 'money');   // throw on incompatible input context
    $info->contentType = ContentType::Html;                // declare what you return
    return '<i>' . htmlspecialchars(number_format($amount, 2)) . ' EUR</i>';
});
```

- `$info->contentType` on entry = the context of the input (`null` = plain variable of unknown type; `ContentType::Html` inside blocks/captures of HTML, etc.). Set it before returning to declare your output type.
- **Filters applied to non-text blocks must be contextual**: `{block|money}`, `{capture|x}`, `{include|x}` and `{translate}` dispatch through `FilterExecutor::filterContent()`, which throws for a classic filter in an HTML context ("used with incompatible content type … try to prepend \|stripHtml").
- Built-in models: `Filters::stripHtml`, `stripTags`, `spaceless`, `indent` in `src/Latte/Essential/Filters.php`.
- A contextual filter generating HTML is responsible for escaping every interpolated input — this is the XSS hot spot.

## Returning HTML safely

A classic filter can mark its output as already-safe HTML by returning `new Latte\Runtime\Html($string)` (`HtmlStringable`), like the built-in `breaklines`. Auto-escaping then skips it. In content-filter position this path is deprecated in favor of a contextual filter that sets `$info->contentType = ContentType::Html`. `|noescape` is not a filter at all — it's stripped at compile time (`$modifier->removeFilter('noescape')`) and flips escaping off for that print.

## Functions

Registered functions are resolved at **compile time**: a compiler pass rewrites matching `FunctionCallNode`s, and `CoreExtension::getCacheKey()` includes the function-name set — so adding/removing functions invalidates compiled templates, and an unregistered name silently falls through to a native PHP function of that name.

A function whose first parameter is typed `Latte\Runtime\Template` receives the current template object automatically (how built-in `hasBlock()`/`hasTemplate()` work):

```php
public function getFunctions(): array
{
    return ['inBlock' => fn(Latte\Runtime\Template $t, string $name) => in_array($name, $t->getBlockNames(), true)];
}
```

No content-type machinery applies to functions — they run inside expressions, and their result is escaped by the surrounding context like any expression.

## Providers

Providers are runtime values/services surfaced to compiled code as `$this->global->{name}`:

```php
public function getProviders(): array
{
    return ['myAcmeDb' => $this->connection];   // vendor-prefix the name — one flat shared namespace
}
```

Use them from a tag's `print()` (e.g. `if ($this->global->myAcmeDevMode) ...`). Core examples: `coreParentFinder` (layout resolution hook), `coreExceptionHandler` ({try} handler), `sandbox` (runtime policy checker), `fn` (the function executor). Same-named providers from later extensions overwrite earlier ones. For per-render setup, override `Extension::beforeRender(Template $template)` instead.

## Runtime dispatch details

Useful when debugging "filter not found" or wrong-arity errors:

- `Latte\Runtime\FilterExecutor` holds all filters. Compiled code calls classic filters as `($this->filters->name)(...)`; content-aware positions call `$this->filters->filterContent('name', $ʟ_fi, $value, ...)`.
- `FilterExecutor::__get` wraps a contextual filter so it also works in classic position (a plain `FilterInfo` is synthesized and discarded).
- `Latte\Runtime\FunctionExecutor` is exposed as `$this->global->fn`; compiled calls look like `($this->global->fn->name)($this, ...args)`.
- Test outside templates with `$latte->invokeFilter('name', [$args])` / `$latte->invokeFunction('name', [$args])`.
