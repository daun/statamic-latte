# Latte from PHP: Engine, Extending, Sandbox, Linter, Types

Reference for integrating and configuring Latte 3.x. Latte 3.1 (current stable line) requires PHP 8.2–8.5; the parallel 3.0.x line supports PHP 8.0+.

## Contents
- Engine setup and rendering
- Typed template parameters and the type system
- Loaders
- Custom filters and functions
- Extensions and custom tags (overview)
- Engine features and options
- Sandbox for untrusted templates
- Linter
- Debugging (Tracy, dump, trace)

## Engine setup and rendering

```php
$latte = new Latte\Engine;
$latte->setTempDirectory('/path/to/cache');   // compiled-template cache; renamed setCacheDirectory() in 3.1.2/3.0.26

$latte->render('template.latte', $params);              // to output
$html = $latte->renderToString('template.latte', $params);
```

- Cache auto-regenerates when sources change; disable checks in production: `$latte->setAutoRefresh(false)`. Stampede-safe. Pre-warm: `$latte->warmupCache('template.latte')`.
- `$params` is an array or an object (below).

## Typed template parameters and the type system

Types in templates are informative — never enforced at runtime — but they power IDE completion and static analysis.

```php
final class CatalogTemplateParameters
{
    public function __construct(
        public string $lang,
        /** @var ProductEntity[] */ public array $products,
        public ?float $price = null,
    ) {}
}
$latte->render('catalog.latte', new CatalogTemplateParameters(lang: 'en', products: $products));
```

```latte
{templateType App\CatalogTemplateParameters}   {* header; declares $lang, $products, $price with types *}
{varType App\ProductEntity $product}            {* type FIRST, then variable *}
{var string $title = $product->getTitle()}
{parameters $a, ?int $b, int|string $c = 10}    {* declares params; undeclared incoming vars are DROPPED *}
```

Scaffolding: `{templatePrint}` renders a suggested parameters class instead of the page (`{templatePrint ParentClass}` to extend); `{varPrint}` suggests `{varType}` tags for local variables (`{varPrint all}` for everything).

## Loaders

```php
// default: FileLoader. Template from strings:
$latte->setLoader(new Latte\Loaders\StringLoader([
    'main.file'  => '{include other.file}',
    'other.file' => '{if true}{$var}{/if}',
]));
$latte->render('main.file', $params);
```

Single-string form: `new StringLoader` and pass the template string to `render()`. Custom loaders (e.g. database-backed) implement `Latte\Loader`.

## Custom filters and functions

```php
$latte->addFilter('shortify', fn(string $s, int $len = 10) => mb_substr($s, 0, $len));
// {$text|shortify:100}

$latte->addFunction('isWeekend', fn(DateTimeInterface $d) => $d->format('N') >= 6);
// {if isWeekend($date)}Weekend!{/if}
```

**Content-aware filters** — first parameter type-hinted `Latte\Runtime\FilterInfo` gets injected; required for filters applied to blocks of HTML. Escape inputs yourself and declare the output content type:

```php
use Latte\Runtime\FilterInfo;
use Latte\ContentType;

$latte->addFilter('money', function (FilterInfo $info, float $amount): string {
    $out = '<i>' . htmlspecialchars(number_format($amount, 2)) . ' EUR</i>';
    $info->contentType = ContentType::Html;   // output is safe HTML, skip auto-escape
    return $out;
});
```

`$info->contentType` is `null` when applied to a plain variable, or `ContentType::Html/Text/JavaScript/...` for block filters. A parameters class can also expose filters/functions via attributes on its methods.

## Extensions and custom tags (overview)

Pick the simplest mechanism: filter (transform a value) → function (compute in expressions) → tag (new language construct) → compiler pass (AST rewriting).

```php
$latte->addExtension(new MyExtension);   // later registrations override earlier names
```

`Latte\Extension` subclasses may implement: `getTags()` (name => node-create callable; pair tags automatically gain `n:name`, `n:inner-name`, `n:tag-name`; `'n:foo'` keys define attribute-only tags), `getFilters()`, `getFunctions()`, `getPasses()`, `getProviders()` (runtime objects as `$this->global->...`), `beforeCompile()`, `beforeRender()`, `getCacheKey()`. Changing an extension class invalidates the template cache. Writing tag node classes is documented at latte.nette.org/en/custom-tags.

Bundled optional extensions:

```php
$latte->addExtension(new Latte\Essential\RawPhpExtension);   // real {php ...} multi-statement tag
$latte->addExtension(new Latte\Essential\TranslatorExtension($translator->translate(...)));  // {_}, {translate}, |translate
$latte->addExtension(new Latte\Bridges\Tracy\TracyExtension); // Tracy panel + nice errors
```

## Engine features and options

```php
$latte->setLocale('en_US');            // needs ext-intl; enables |localDate, ICU |number, locale-aware |sort/|bytes
$latte->setExceptionHandler(fn(Throwable $e, Latte\Runtime\Template $t) => $logger->log($e)); // logs {try}/sandbox exceptions
$latte->enablePhpLinter('/usr/bin/php'); // php -l on compiled output during compile()
$latte->addProvider('coreParentFinder', fn(Latte\Runtime\Template $t) => 'layout.latte'); // automatic layout lookup; templates opt out via {layout none}
```

`Latte\Feature` flags via `$latte->setFeature(...)` / `hasFeature(...)` (enum introduced in 3.1.2/3.0.26):

- `StrictTypes` — `declare(strict_types=1)` in compiled code (default ON since 3.1).
- `StrictParsing` — all unclosed/mismatched HTML elements are compile errors; `$this` disabled.
- `ScopedLoopVariables` *(3.1.3+)* — foreach variables restored/unset after the loop.
- `Dedent` *(3.1.3+)* — strip structural indentation inside paired tags.
- `MigrationWarnings` — 3.0→3.1 attribute-behavior warnings (`|accept` filter silences per-value).

## Sandbox for untrusted templates

Deny-by-default allow-list over tags, n:attributes, filters, functions, methods, properties:

```php
$policy = new Latte\Sandbox\SecurityPolicy;
$policy->allowTags(['block', 'if', 'else', '=']);        // '=' is the print tag
$policy->allowFilters($policy::All);
$policy->allowFunctions(['trim', 'strlen']);
$policy->allowMethods(User::class, ['isLoggedIn']);      // covers subclasses
$policy->allowProperties(Row::class, $policy::All);
$latte->setPolicy($policy);
```

- Baseline: `SecurityPolicy::createSafePolicy()` — standard tags except `contentType, debugbreak, dump, extends, import, include, layout, php, sandbox, embed, templatePrint, varPrint, snippet, snippetArea`; standard filters except `datastream, noescape, nocheck`.
- Activate per include — `{sandbox 'untrusted.latte', a: 1}` (explicit params only; surrounding templates stay unrestricted) — or globally: `$latte->setSandboxMode()`.
- Violations throw `Latte\SecurityViolationException` (tags/filters at compile time; methods/properties at runtime). Pair with `setExceptionHandler()` and `enablePhpLinter()`.
- Always forbidden regardless of policy: `new`, `$this`, `$$var`, `|noescape`.
- Known gap: implicit `__toString()` is not policy-checked (`{$obj}`, `{$obj . '!'}`, `{$obj|upper}` all trigger it) — never expose objects whose `__toString()` has side effects or leaks data.

## Linter

```bash
vendor/bin/latte-lint <path>          # flags: --strict, --debug
```

Compiles every template in a directory and reports syntax/compile errors and deprecation warnings.

*(Unreleased — master post-3.1.4: the linter moves to a `Latte\Linting` namespace with `Latte\Tools\Linter` kept as a BC alias, and gains first-class WARNING checks: unknown filters/functions/classes/methods/class constants/static properties/global constants (SymbolCheck), and statically-named template paths in `{include}/{import}/{layout}/{embed}/{sandbox}/{include ... from}` that don't resolve (TemplateReferenceCheck; dynamic names skipped, missing *blocks* not reported), plus a PHP lint of compiled output.)*

With custom extensions, build your own runner with `Latte\Tools\Linter`:

```php
$linter = new Latte\Tools\Linter;
$linter->getEngine()->addExtension(new MyExtension);
exit($linter->scanDirectory($argv[1] ?? '.') ? 0 : 1);
```

## Debugging (Tracy, dump, trace)

```php
Tracy\Debugger::enable();
$latte->addExtension(new Latte\Bridges\Tracy\TracyExtension);
```

Gives error screens with template line/column, a Tracy Bar panel listing rendered templates + variables, and click-through to the (readable, IDE-steppable) compiled PHP. In templates: `{dump $var}`, `{debugbreak}`, `{trace}`. Auto-escape opt-out from PHP: pass `new Latte\Runtime\Html($trusted)` or implement `Latte\Runtime\HtmlStringable` (its `__toString()` must return already-safe HTML).
