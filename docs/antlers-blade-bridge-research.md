# Statamic ↔ Blade Bridge — Final Consolidated Report

> Handoff doc for building a Blade-style bridge for a **different** templating engine,
> modeled on how Statamic integrates its proprietary Antlers engine with Laravel Blade.
> Every file path is in this repo (`statamic/statamic` core, `src/`). All code claims
> below were verified first-hand against source. Merges two prior research passes and
> resolves their gaps. Points still ambiguous are flagged with **⚠️ FLAG**.

---

## 0. The big picture — there are FIVE distinct bridges

Don't conflate them. Statamic crosses the Antlers/Blade boundary in five separate ways,
in **both directions**:

| # | Surface | Direction | Mechanism | Where |
|---|---------|-----------|-----------|-------|
| A | `{{ Statamic::tag('collection')->...->fetch() }}` | use Antlers tag *in Blade* | runtime facade → `FluentTag` builder | §3 |
| B | `@tags([...])`, `@cascade`, `@frontmatter` | use Antlers tag *in Blade* | `Blade::directive` → runtime helper | §4 |
| C | `<s:collection from="blog">…</s:collection>` | use Antlers tag *in Blade* | `Blade::precompiler` → PHP codegen | §5–7 |
| D | `@antlers … @endantlers` | raw Antlers island *in Blade* | `Blade::precompiler` → temp `.antlers.html` | §8 |
| E | `<x-alert />`, `<x-slot:…>` | use **Laravel Blade component** *in Antlers* | Antlers `ComponentCompiler` → `%component_proxy` tag | §10 |

**Crucial distinction for your Q2.** "Blade components" (`x-`) appear in two unrelated places:
- **Inside Blade files**, Laravel's own `<x-*>` compiler is untouched — Statamic ignores it.
  Statamic's *own* component-like syntax there is `<s:*>` / `<statamic:*>` (bridge C), which
  exposes **Antlers tags** as Blade-component-looking tags. These are **not** Laravel components.
- **Inside Antlers files**, Statamic adds real support for Laravel `<x-*>` Blade components
  (bridge E) by lowering them to an internal `%component_proxy` Antlers tag that drives
  Laravel's actual component runtime.

So bridge **C** = "Antlers tags dressed as Blade components" (in Blade).
Bridge **E** = "real Laravel Blade components" (in Antlers). Keep these separate.

Engine *selection* (which file goes to which engine) is orthogonal: `.antlers.html` →
Statamic Antlers engine, `.blade.php` → Laravel Blade. `ViewServiceProvider::boot()` registers
the Antlers extensions (`Engine::EXTENSIONS`). The bridges are about crossing *within* a file.

---

## 1. Important semantic note: `{{ }}` is NOT reinterpreted

In a Blade file, `{{ ... }}` stays **Laravel Blade echo** — escaped via `htmlspecialchars`,
accepts any PHP expression. Statamic does **not** make Blade braces understand Antlers. So:

```blade
{{ collection:blog }}        {{-- ❌ NOT valid: this is just a PHP expression in Blade --}}
{{ Statamic::tag('collection:blog')->fetch() }}   {{-- ✅ the supported way --}}
```

**Escaping differs across the boundary** (document this in any new bridge):
- Blade `{{ $x }}` → escaped, `{!! $x !!}` → raw.
- Antlers `{{ x }}` → **unescaped by default**.

This asymmetry is a real source of bugs; call it out at every boundary.

---

## 2. Registration — where everything wires up

**`src/Providers/ViewServiceProvider.php`** — `boot()` + `registerBladeDirectives()`:

```php
public function boot()
{
    ViewFactory::addNamespace('compiled__views', storage_path('framework/views'));
    $this->registerBladeDirectives();

    // (C) <s:…>/<statamic:…> component-tag precompiler
    Blade::precompiler(fn ($content) => (new StatamicTagCompiler())->compile($content));
    // (D) @antlers…@endantlers precompiler
    Blade::precompiler(fn ($content) => AntlersBladePrecompiler::compile($content));
    // … plus Antlers engine extension registration
}

public function registerBladeDirectives()
{
    Blade::directive('tags',    fn ($e) => "<?php extract(\Statamic\View\Blade\TagsDirective::handle($e)) ?>");
    Blade::directive('cascade', fn ($e) => "<?php extract(\Statamic\View\Blade\CascadeDirective::handle($e)) ?>");
    Blade::directive('frontmatter', fn ($e) => "<?php /* merge frontmatter into \$view */ ?>");
    Blade::directive('recursive_children', /* nav recursion, §7 */);
}
```

The **reverse** bridge (E) is *not* registered here — it's instantiated inside the Antlers
runtime: **`src/View/Antlers/Language/Runtime/RuntimeParser.php`** ctor (`new ComponentCompiler()`)
and invoked in `renderText()` (line 344: `$text = $this->componentCompiler->compile($text);`).

Laravel APIs leaned on:
- **`Blade::precompiler(callable)`** — runs on the *raw template string* before Blade compiles.
  This is the linchpin that lets Statamic invent `<s:…>` without fighting Laravel's `x-` compiler.
- **`Blade::directive(name, callable)`** — `@name(...)` → PHP.
- Facade entry points `Statamic::tag()` / `Statamic::modify()`.

> Unrelated: `CpServiceProvider` has a *separate* `<ui-…>` precompiler via
> `Blade::prepareStringsForCompilationUsing` for the control panel. Ignore it — not part of
> the public templating bridge.

---

## 3. Bridge A — runtime fluent tag API (`Statamic::tag()`)

```blade
@foreach (Statamic::tag('collection:pages')->limit(3) as $page)
    <li>{{ $page->title }}</li>
@endforeach

{{ Statamic::tag('collection:pages')->param('title:contains', 'pizza')->fetch() }}

@php($tag = Statamic::tag('collection:pages')->paginate(2)->as('pages')->fetch())
@foreach ($tag['pages'] as $page) … @endforeach
{!! $tag['paginate']['auto_links'] !!}
```

- Tag name carries the method via colon: `collection:pages` → tag `collection`, method `pages`;
  no colon ⇒ method `index`.
- Params are fluent **magic methods**: `->limit(3)` → param `limit=3`; multi-word camelCase →
  snake_case (`->queryScope(...)` → `query_scope`). Use `->param('title:contains','x')` /
  `->params([...])` for keys not expressible as PHP method names (colon filters).
- **Lazy + iterable**: `->fetch()` runs it; `@foreach` runs it via `IteratorAggregate`; string
  context runs it via `__toString`; `$tag['k']` via `ArrayAccess`.

`src/Statamic.php`:
```php
public static function tag($name)     { return FluentTag::make($name); }
public static function modify($value) { return Modify::value($value); }
```

`src/Tags/FluentTag.php` (implements `ArrayAccess`, `IteratorAggregate`):
```php
public static function make($name) { $i = app(self::class); $i->name = $name; return $i; }

public function fetch() {
    if ($this->fetched) return $this->fetched;
    $name = $this->name;
    if ($pos = strpos($name, ':')) {                  // split name:method
        $originalMethod = substr($name, $pos + 1);
        $method = Str::camel($originalMethod);
        $name = substr($name, 0, $pos);
    } else { $method = $originalMethod = 'index'; }

    $tag = app(Loader::class)->load($name, [
        'parser' => null, 'params' => $this->params, 'content' => $this->content,
        'context' => $this->context, 'tag' => "$name:$originalMethod", 'tag_method' => $originalMethod,
    ]);
    return $this->fetched = $tag->$method();
}
public function __call($param, $args) { $this->param(Str::snake($param), $args[0] ?? true); return $this; }
public function getIterator() { $o = $this->fetch(); return $o instanceof Traversable ? $o : new ArrayIterator($o); }
public function __toString()  { return (string) $this->fetch(); }
```

`src/Tags/Loader.php` — central resolution: looks up handle in `app('statamic.tags')`,
instantiates from container, calls `setProperties(parser, params, content, context, tag, tag_method)`.
**Every bridge funnels tag execution through this Loader**, so behavior is identical across surfaces.

---

## 4. Bridge B — `@tags` / `@cascade` / `@frontmatter` directives

Compile to `<?php extract(Handler::handle(...)) ?>` injecting variables.

`src/View/Blade/TagsDirective.php`:
```php
public static function handle($tags): array {
    return Collection::wrap($tags)->mapWithKeys(function ($value, $key) {
        if (is_array($value) && count($value) > 0) { $tag = array_keys($value)[0]; $params = array_values($value)[0]; }
        elseif (is_string($value))                 { $tag = $value; $params = []; }
        $var = is_string($key) ? $key : Str::camel(str_replace(':', '_', $tag));  // 'collection:blog' → $collectionBlog
        return [$var => Statamic::tag($tag)->params($params)->fetch()];
    })->all();
}
```
```blade
@tags('collection:blog')                                    {{-- → $collectionBlog --}}
@tags(['posts' => ['collection:blog' => ['limit' => 5]]])   {{-- → $posts --}}
```

`@cascade` (`CascadeDirective`) extracts Statamic's cascade (site, page, globals…) — `@cascade`
for all, `@cascade(['title','foo' => 'default'])` for a subset. These are thin sugar over bridge A.

---

## 5. Bridge C — component-tag syntax `<s:…>` (Antlers tags as Blade components)

### 5.1 Surface

```blade
<s:collection from="pages" limit="2" sort="title:desc">{{ $title }}</s:collection>
<s:collection :from="$collection" limit="2"> … </s:collection>   {{-- dynamic attr --}}
<s:nav:collection:pages>{{ $title }}</s:nav:collection:pages>     {{-- method via colon --}}
<s:cache:bust />                                                 {{-- self-closing scalar tag --}}

<s:collection from="blog"> … <s:no_results>None</s:no_results> </s:collection>
<s:partial:card><s:slot:header>Header</s:slot:header>Body</s:partial:card>
<s:nocache> … </s:nocache>
```
Prefixes: `s:`, `statamic:`, `s-`, `statamic-`. ⚠️ **FLAG:** the docs/Codex also show a
shorthand dynamic attr `<s:collection :$from>` (binds `from` from `$from`). The Antlers-side
compiler definitely handles `ShorthandDynamicVariable` (see §10), and the Blade-side relies on
`stillat/blade-parser`'s `AttributeCompiler` which supports it, but I did not execute a Blade-side
test to confirm `:$var` end-to-end. Treat as "very likely supported, unverified."

### 5.2 Parsing — uses `stillat/blade-parser`, NOT Laravel's component compiler

`src/View/Blade/StatamicTagCompiler.php`:
```php
protected array $statamicTags = ['statamic', 's'];

public function compile(string $template): string {
    if (! Str::contains($template, ['<statamic:', '<statamic-', '<s:', '<s-'])) return $template;

    return (new DocumentParser())
        ->registerCustomComponentTags($this->statamicTags)   // teach the parser our prefixes
        ->onlyParseComponents()
        ->parseTemplate($template)->toDocument()->getRootNodes()
        ->map(function ($node) {
            if (! $node instanceof ComponentNode)                       return $node->unescapedContent;
            if (! in_array(mb_strtolower($node->componentPrefix), $this->statamicTags))
                                                                        return $node->outerDocumentContent;
            if ($node->isClosingTag && ! $node->isSelfClosing)          return '';
            if ($node->tagName === 'nocache')                           return $this->compileNocache($node);
            if ($this->isPartial($node))                                return $this->compilePartial($node);
            if ($this->interceptNav && $this->isStructure($node->tagName)) return $this->compileNav($node);
            return $this->compileComponent($node);                      // general case
        })->join('');
}
```
`extractMethodNames()` splits `tagName` on first `:` → `[$name, camelMethod, $originalMethod]`
(`collection:pages` → `['collection','pages','pages']`; bare `collection` → method `index`).

### 5.3 Attribute → param compilation

```php
$this->attributeCompiler = (new AttributeCompiler())
    ->prefixEscapedParametersWith('attr:')
    ->wrapResultIn(['as','scope'], fn ($v) => "\\Statamic\\View\\Blade\\StatamicTagCompiler::adjustDynamicVariableName($v)");

protected function compileParameters(array $params): string {
    return '\Statamic\View\Blade\BladeTagHost::filterParams(' . $this->attributeCompiler->compile($params) . ')';
}
```
- Static attrs compile to literal array entries; `:attr` to live PHP expressions (Laravel `:` convention).
- `filterParams()` strips "void" sentinel values (§9) so an unset dynamic attr disappears rather
  than passing `null`.

---

## 6. Bridge C codegen — the generated PHP for a general `<s:…>` component

`compileComponent()` in **`src/View/Blade/Concerns/CompilesComponents.php`** emits PHP with
`$tagName`, `$params`, `$tagMethod`, `#compiled#` (inner Blade), etc. swapped in. Abridged
(the real thing handles full loop var save/restore):

```php
<?php
$__statamicResultTagContent = <<<'CONTENT'
#compiledEncoded#      // inner blade, base64'd to survive nested heredocs
CONTENT;

$host = new \Statamic\View\Blade\BladeTagHost(get_defined_vars());
$host->setContent(base64_decode($__statamicResultTagContent));
$host->setTag(
    app(\Statamic\Tags\Loader::class)->load('$tagName', [
        'parser' => null, 'params' => $params, 'content' => '',
        'context' => [], 'tag' => '$fullTagName', 'tag_method' => $originalMethod,
    ]), $tagMethod
)->setIsPair($isPair)->setParams($params);

if (isset($__statamicOverrideTagResultValue)) {     // nav recursion hook (§7)
    $host->setValue($__statamicOverrideTagResultValue); unset($__statamicOverrideTagResultValue);
} else { $host->render(); }                          // run the tag

// --- dispatch on RESULT SHAPE ---
if ($host->isAssociativeArray()) {
    foreach ($host->getValue() as $__key => $__value) { $$__key = $__value; }   // expose row as vars
} elseif ($host->isArray()) {
    $__currentLoopData = $host->getValue();
    if ($host->isEmpty()) { ?>#compiledEmpty#<?php }                            // <s:no_results>
    else {
        $__env->addLoop($__currentLoopData);
        foreach ($__currentLoopData as $__loopValue) {
            $__env->incrementLoopIndices();
            $loop = $__env->getLastLoop();                                       // Blade $loop works!
            // snapshot vars; expose row keys (or ${scope} if scope="x"); render inner; restore vars
            ?>#compiled#<?php
        }
        $__env->popLoop(); $loop = $__env->getLastLoop();
    }
} elseif ($host->canRenderAsString()) {
    echo $host->renderString();                                                  // scalar → echo
}
if ($host->shouldRenderCompiledContent()): ?>#compiled#<?php endif;             // assoc / bool-true pairs
// re-expose protected vars (e.g. $page) afterward
```

**Key design properties:**
- **Result-shape polymorphism.** One tag may return scalar / assoc array / list. `BladeTagHost`
  inspects the value and the generated PHP branches: echo scalar / expose assoc keys as vars /
  loop a list rendering inner content per row.
- **Native `$loop`.** Hooks `$__env->addLoop/incrementLoopIndices/popLoop`, so Blade `$loop->index`,
  `->first`, etc. work inside `<s:…>` pairs.
- **Variable hygiene.** Snapshots `get_defined_vars()`, exposes each row's keys as locals (or
  `${scope}` if `scope="x"`), restores afterward so loops don't leak/clobber outer vars. `page`
  is always protected/restored.
- **`as` / `scope`** alias results (compile-time `wrapResultIn` + runtime `getValue()`/`hasScope()`).
- **Inner content base64'd** into a nowdoc to survive nested heredocs — a practical trick to copy.

### 6.1 Runtime host — `src/View/Blade/BladeTagHost.php`
```php
public function render(): mixed {
    $this->tag->isPair = $this->isPair;
    $this->tag->setContext($this->context);
    $this->tag->setTagRenderer(app(TagRenderer::class));    // ← tells the tag "render nested in Blade"
    if ($this->isPair) { $this->tag->setContent($this->content); }
    $this->originalValue = $this->tag->{$this->method}();
    return $this->tagValue = self::adjustBladeValue($this->originalValue);
}
public static function adjustBladeValue(mixed $v): mixed {   // normalize Statamic types → plain PHP
    if ($v instanceof Value)       $v = $v->value();
    if ($v instanceof Collection)  $v = $v->all();
    if ($v instanceof Augmentable) $v = $v->toDeferredAugmentedArray();
    if ($v instanceof Arrayable)   $v = $v->toArray();
    return $v;
}
// + isAssociativeArray/isArray/isEmpty/canRenderAsString/getValue/hasScope/getScopeName/
//   hasAlias/getAlias/filterParams/shouldAddValue/setValue …
```
`adjustBladeValue()` is the **type-normalization boundary** — without it Blade chokes on
Statamic's rich value objects. `setValue()` lets nav recursion inject precomputed children.

### 6.2 The engine-abstraction seam — `TagRenderer`
`src/View/Blade/TagRenderer.php`:
```php
class TagRenderer implements TagRendererContract {
    public function render(string $contents, array $data): string { return Blade::render($contents, $data); }
    public function getLanguage(): string { return 'blade'; }
}
```
Base `src/Tags/Tags.php`:
```php
protected function templatingLanguage()    { return $this->tagRenderer ? $this->tagRenderer->getLanguage() : 'antlers'; }
protected function isAntlersBladeComponent(){ return $this->templatingLanguage() === 'blade'; }
```
A tag detects it's in Blade context and renders its pair-content via `$this->tagRenderer->render(...)`
instead of Antlers. **This single contract is what lets one tag codebase serve both engines** —
the most important thing to replicate for a new engine.

---

## 7. Partials, slots, nav, nocache (bridge C sub-cases)

- **`<s:partial:card>`** (`Concerns/CompilesPartials.php`): forces param `src="card"`, method `index`
  (the `partial` tag). `<s:slot:header>…</s:slot:header>` children are extracted, each compiled
  separately, passed as a **dynamic param** = `new HtmlString(Blade::render($slotBody, get_defined_vars()))`
  — slots are pre-rendered Blade strings handed to the partial as variables. Reserved forwarding
  methods: `exists`, `if_exists`.
- **`<s:nav>`/`<s:structure>`/`<s:children>`** (`Concerns/CompilesNavs.php`): wraps the compiled body
  in a **named recursive PHP closure**:
  ```php
  $___statamicNavCallback = function ($scope, $___statamicNavCallback) { extract($scope); ob_start(); ?>{body}<?php return ob_get_clean(); };
  echo $___statamicNavCallback(get_defined_vars(), $___statamicNavCallback);
  ```
  The `@recursive_children` directive re-invokes it with `depth+1` and
  `__statamicOverrideTagResultValue => $children` — hence the `isset($__statamicOverrideTagResultValue)`
  branch in §6 (inject children via `setValue()` instead of re-querying). Inner compiler runs with
  `interceptNav=false` to avoid infinite re-interception.
- **`<s:nocache>`** (`Concerns/CompilesNocache.php`): compiles inner content to
  `storage/framework/views/_nocache{sha}.blade.php`, emits `@nocache('compiled__views::…')`.

---

## 8. Bridge D — raw Antlers island: `@antlers … @endantlers`

`src/View/Blade/AntlersBladePrecompiler.php`: extracts the block, writes it to
`storage/framework/views/antlers_{sha}.antlers.html`, replaces it with
`@include('compiled__views::antlers_{sha}')`. Raw Antlers in Blade is handled by **writing a temp
Antlers partial and including it** — engines stay cleanly separated, no in-place cross-compilation.
(`compiled__views` namespace added in `ViewServiceProvider::boot()`.) **Generalizable lesson:** raw
foreign-template islands = write a temp partial in the foreign engine, include via host view system.

---

## 9. The "void" sentinel

`helpers.php::void()` returns `'void::'.$envId`; `BladeTagHost::filterParams()` drops keys whose value
is void. Lets an unset dynamic attribute be *removed* rather than passed as `null`.
`helpers.php` also exports free functions `tag()`, `modify()`, `value()` (the last unwraps
`Value`/`Values`/`ArrayableString`/`FluentTag`/`Modify`).

---

## 10. Bridge E — Laravel `<x-*>` Blade components INSIDE Antlers (the reverse direction)

> This is the literal answer to "how does the `x-` Blade component bridge work." It was missing
> from the first research pass; fully verified here.

### 10.1 Surface (inside an `.antlers.html` file)
```antlers
<x-alert title="The Title" />
<x-card title="The Title" class="mt-4">Content</x-card>
<x-named_slots>
    <x-slot:header class="header-classes">Header</x-slot:header>
    Slot Content
</x-named_slots>
```
Class components, anonymous components, attribute bags, named slots, and `@aware` all work — these
are **real Laravel Blade components** driven by Laravel's own component runtime.

### 10.2 Pipeline
1. **`src/View/Antlers/Language/Parser/ComponentCompiler.php`** — invoked from
   `RuntimeParser::renderText()` (line 344) on the raw Antlers text. Early-exits unless the template
   contains `<s-`, `<s:`, `<statamic-`, `<statamic:`, `<x:`, `<x-`. Parses with `stillat/blade-parser`.
   - `componentPrefix === 'x'` → `compileBladeComponent()` (the Laravel-component path).
   - prefix in `['statamic','s','flux']` → `compileComponent()` which lowers `<s:tag>` to Antlers
     `{{ %tag … }}` syntax (so `<s:*>` also works *inside Antlers*, via a different compiler than
     bridge C). ⚠️ **FLAG:** `flux` is included here (Livewire Flux UI). It routes through the same
     `{{ % … }}` lowering as `s`/`statamic`, i.e. **not** through `%component_proxy`. I did not fully
     trace how `<flux:button>` ultimately resolves; treat Flux support as "present but mechanism
     not fully traced." Not relevant to a generic new-engine bridge — mentioned for completeness.

2. **`src/View/Antlers/Language/Parser/Concerns/CompilesBladeComponents.php`** — rewrites Laravel
   components into an internal `%component_proxy` Antlers tag:
   ```antlers
   <x-alert title="The Title" />
   ⇩
   {{ %component_proxy:index title="The Title" component_name___="alert" /}}

   <x-slot:header> … </x-slot:header>
   ⇩
   {{ %component_proxy:component_slot component_slot___="header" }} … {{ /%component_proxy:component_slot }}
   ```
   `compileParameters()` maps each parameter type: static `Parameter`, boolean `Attribute`,
   `ShorthandDynamicVariable` (`:$x` → `:x="x"`), `DynamicVariable` (`:x="expr"`),
   `InterpolatedValue`, and `UnknownEcho` (`{{ x }}` triple-echo). The component name and slot
   name are smuggled through the reserved params `component_name___` / `component_slot___`.

3. **`src/Tags/ComponentProxy.php`** (registered in `ExtensionServiceProvider` core tag list) —
   the runtime that drives Laravel's component system:
   ```php
   public function index() {
       $__env = $this->context['__env'] ?? view();
       $__env->incrementRender();
       $componentName = $this->params['component_name___'];

       $tagCompiler = new ComponentTagCompiler(                    // reuse Laravel's resolver
           $blade->getClassComponentAliases(), $blade->getClassComponentNamespaces(), $blade);
       $className = $tagCompiler->componentClass($componentName);

       $data = $this->params->except('component_name___')->all();
       $attributes = new ComponentAttributeBag($data);
       $scopeData = array_merge($this->context->all(), $data);

       if (! class_exists($className)) { $className = AnonymousComponent::class; /* isAnonymous */ }

       if ($ctor = (new ReflectionClass($className))->getConstructor()) {           // match ctor params
           $names = collect($ctor->getParameters())->map->getName()->all();
           $attributes = $attributes->except($names);
           $constructorParameters = collect($scopeData)->only($names)->all();
       }
       // anonymous: pass view + data through

       $component = $className::resolve($constructorParameters + (array) $attributes->getIterator());
       $component->withName($componentName);
       $__env->startComponent($component->resolveView(), $component->data());
       $component->withAttributes($attributes->getAttributes());

       if ($this->content) {                                       // render child content as Antlers
           self::$componentStack[] = [$component, $contextData];
           echo $this->parse($contextData);
       }
       $result = $__env->renderComponent();
       $__env->decrementRender(); $__env->flushStateIfDoneRendering();
       return ltrim($result);
   }

   public function componentSlot() {                               // named slots
       $contextData = self::$componentStack[array_key_last(self::$componentStack)][1];
       $__env->slot($this->params->get('component_slot___'), null, $this->params->except('component_slot___')->all());
       echo $this->parse($contextData);                           // slot body parsed as Antlers
       $__env->endSlot();
   }
   ```
   It reuses Laravel's **`ComponentTagCompiler`** for class/alias/namespace resolution, falls back
   to **`AnonymousComponent`** when no class exists, builds a **`ComponentAttributeBag`**, resolves
   constructor params from Antlers context + attrs, then drives Laravel's component lifecycle
   (`startComponent` → `renderComponent`). Slots use Laravel's `$__env->slot()/endSlot()` with a
   static `$componentStack`. **Child/slot content is parsed as Antlers** (`$this->parse(...)`),
   so an Antlers child can live inside a Blade component and vice-versa.

### 10.3 Tests proving behavior
- `tests/Antlers/Components/BladeComponentsTest.php` — anonymous + class components, attributes,
  `@aware`, Blade-root/Antlers-child and the inverse.
- `tests/Antlers/Components/SlotContentsTest.php` — default + named slots, component instance
  available inside slot.

**Lesson:** to render the host framework's native components from your engine, don't reimplement
the component system — **lower your `<x-…>` syntax to a synthetic tag that drives the host's own
component compiler/runtime** (resolver, anonymous fallback, attribute bag, ctor-param matching,
slot stack), parsing child/slot bodies back through your engine.

---

## 11. Modifiers in Blade

```blade
{{ Statamic::modify($content)->stripTags()->backspace(1)->ensureRight('!!!') }}
@php(use function Statamic\View\Blade\modify)
{{ modify('test')->stripTags()->safeTruncate([42, '...']) }}
```
`Statamic::modify($v)` → `Modify::value($v)`; each fluent method applies a modifier immediately,
args = modifier params, multi-word in camelCase. `Modify::__call` → `runModifier()` →
`$class->$method($value, $params, $context)`; `fetch()`/`__toString()` resolve.
`src/Modifiers/Loader.php` snake-cases the name, looks up `app('statamic.modifiers')`, and supports
core modifiers registered as `CoreModifiers@methodName` (split on `@`).

---

## 12. Laravel Blade component background (the native mechanism built upon)

- **Class components** (`app/View/Components`, views in `resources/views/components`) vs **anonymous
  components** (`resources/views/components/*.blade.php`). Nested: `<x-forms.input />` →
  `components/forms/input.blade.php`.
- Attributes flow through `ComponentAttributeBag` (`{{ $attributes->merge([...]) }}`, `->class([...])`).
- Slots: `{{ $slot }}`, `<x-slot:header>…</x-slot:header>`, scoped slots.
- Package/namespace registration: `Blade::component('alias', Cls::class)`,
  `Blade::componentNamespace('Vendor\\Pkg\\Components', 'pkg')` → `<x-pkg::calendar />`;
  `Blade::anonymousComponentNamespace(...)` / `anonymousComponentPath(...)` for file-based.
- Resolution internals reused by bridge E: `ComponentTagCompiler::componentClass()`,
  `AnonymousComponent`, `$__env->startComponent()/renderComponent()/slot()`.

Statamic does **not** override any of this inside Blade files; it only owns `<s:…>`/`<statamic:…>`.

---

## 13. Porting blueprint for the new templating-engine add-on

### 13a. Reuse VERBATIM (engine-agnostic core)
`Statamic::tag()` · `FluentTag` (lazy, `IteratorAggregate`/`ArrayAccess`/`__toString`) ·
`Statamic::modify()`/`Modify` · `Modifiers\Loader` · `Tags\Loader` · directive handlers
(`TagsDirective`, `CascadeDirective`) · the **logic** of `BladeTagHost` (esp. `adjustBladeValue`
+ result-shape predicates) · the **`TagRenderer` contract** pattern.

### 13b. Rewrite for engine X (target-specific)
The precompiler/AST rewrite (`StatamicTagCompiler` + `Concerns/*`) and the generated code strings —
they're tied to Blade's compiled-PHP runtime (`$__env`, `$loop`, heredoc inner content). **Keep the
structure, swap the codegen:** parse custom tags → split `name:method` → compile attrs (static vs
`:dynamic`) → emit host call → branch on result shape.

### 13c. Step-by-step
1. **Engine selection** — register X's file extension → engine (mirror `addExtension`/`Engine::EXTENSIONS`).
2. **Runtime fluent API** — reuse `Statamic::tag()`/`FluentTag`/`Modify` as-is; if X has its own
   "call a function" hook, wire it to `FluentTag::make($name)->params(...)->fetch()`.
3. **Directive-equivalents** — port `@tags`/`@cascade` as thin wrappers over the existing handlers.
4. **Custom component syntax** — find X's analog of `Blade::precompiler` (a raw-string rewrite hook).
   Mirror `StatamicTagCompiler`: parse out your prefix, split `name:method`, compile attrs to a params
   expr, generate code that builds a host, runs the tag via `Loader`, and branches on result shape
   (scalar→echo, assoc→vars, list→loop with `no_results` empty branch).
5. **Reuse `BladeTagHost` as a template** → `XTagHost` with identical responsibilities; lift
   `adjustValue()` and the shape predicates near-verbatim. **Value normalization is mandatory.**
6. **Implement `TagRenderer` for X** — `render(contents,data):string` rendering X templates +
   `getLanguage():'x'`. Inject via `$tag->setTagRenderer()`. Generalize base `Tags::templatingLanguage()`
   (or add `isX()`) so existing tag classes render pair-content through X.
7. **Slots / no_results / nav recursion** — port only if needed; independent concerns over the same compiler.
8. **(Optional) reverse bridge** — to render host-framework components from X, replicate bridge E:
   lower `<x-…>` to a synthetic proxy tag that drives the host component compiler/runtime; parse
   child/slot bodies back through X.
9. **Document escaping** at every boundary (§1).

### 13d. Design rules distilled from Statamic
- **Don't overload host echo syntax.** Keep `{{ }}` meaning the host's meaning; expose tags via an
  explicit fluent API + a *new* prefix (`<t:…>`/`<engine:…>`), never `<x-…>` (host owns it).
- **One `Loader`** funnels all surfaces → consistent behavior.
- **Compile-time rewrite + thin runtime host** beats a fat runtime interpreter (keeps host `$loop`,
  scoping, performance).
- **A single renderer-contract seam** (`TagRenderer`) is what makes one tag codebase multi-engine.

---

## 14. File index

| Path | Role |
|------|------|
| `src/Providers/ViewServiceProvider.php` | registers precompilers + directives + Antlers extensions |
| `src/Statamic.php` | facade `tag()`, `modify()` |
| `src/Tags/FluentTag.php` | lazy fluent tag builder |
| `src/Tags/Loader.php` | central tag resolution |
| `src/Tags/Tags.php` | base tag; `setTagRenderer`, `templatingLanguage`, `isAntlersBladeComponent` |
| `src/Modifiers/Modify.php`, `src/Modifiers/Loader.php` | fluent modifier chain + lookup |
| `src/View/Blade/helpers.php` | `tag()`/`modify()`/`value()`/`void()` |
| `src/View/Blade/TagsDirective.php`, `CascadeDirective.php` | `@tags`, `@cascade` |
| `src/View/Blade/AntlersBladePrecompiler.php` | `@antlers..@endantlers` → temp Antlers include |
| `src/View/Blade/StatamicTagCompiler.php` | `<s:…>`/`<statamic:…>` precompiler entry (bridge C) |
| `src/View/Blade/BladeTagHost.php` | runtime host: run tag, normalize value, shape predicates |
| `src/View/Blade/TagRenderer.php` | renders nested content through Blade (engine seam) |
| `src/View/Blade/Concerns/CompilesComponents.php` | general `<s:…>` codegen (loop/var/`$loop`) |
| `src/View/Blade/Concerns/CompilesPartials.php` | `<s:partial:…>` + `<s:slot:…>` |
| `src/View/Blade/Concerns/CompilesNavs.php` | recursive nav closure + `@recursive_children` |
| `src/View/Blade/Concerns/CompilesNocache.php` | `<s:nocache>` |
| `src/View/Antlers/Language/Runtime/RuntimeParser.php` | invokes the reverse compiler (line 344) |
| `src/View/Antlers/Language/Parser/ComponentCompiler.php` | `<x-…>`/`<s:…>`/`<flux:…>` in Antlers (bridge E) |
| `src/View/Antlers/Language/Parser/Concerns/CompilesBladeComponents.php` | `<x-…>` → `%component_proxy` |
| `src/Tags/ComponentProxy.php` | drives Laravel's component runtime from Antlers |
| `src/Providers/ExtensionServiceProvider.php` | registers tags incl. `ComponentProxy`; `statamic.tags`/`.modifiers` bindings |
| `config/templates.php` | default language (`antlers` vs `blade`) |
| `tests/Antlers/Components/BladeComponentsTest.php`, `SlotContentsTest.php` | bridge-E behavior tests |

---

## 15. Verification status & flags for discussion

**Confirmed first-hand (read the source):** bridges A–E all exist and work as described;
registration points; the `%component_proxy` rewrite + `ComponentProxy` runtime; `RuntimeParser`
line-344 invocation; `Modifiers\Loader` `CoreModifiers@` handling; `BladeTagHost` value normalization
and shape dispatch; nav recursion closure; nocache; `@antlers` temp-include.

**⚠️ Open items — each with verification options and a recommended next step.**

### FLAG 1 — Blade-side `:$shorthand` (`<s:collection :$from>`)
- **Status:** confirmed on the Antlers side (`CompilesBladeComponents::compileParameters` handles
  `ParameterType::ShorthandDynamicVariable`). On the **Blade** side it rides on
  `stillat/blade-parser`'s `AttributeCompiler` inside `StatamicTagCompiler`; not executed end-to-end.
- **Why it matters:** if the new engine copies the Blade-side attribute-compilation path, you need to
  know whether `:$x` shorthand is free or needs explicit handling.
- **Verification options:**
  - **(a) Render test (recommended).** Add a test under `tests/View/Blade/` that renders
    `<s:collection :$from>{{ $title }}</s:collection>` with `$from = 'pages'` in scope and asserts
    output. Fastest definitive answer (~15 min).
  - **(b) Compiler-output assertion.** Call `(new StatamicTagCompiler())->compile('<s:collection :$from />')`
    and inspect the generated PHP for a `from => $from` mapping. Cheaper, no fixtures, but only proves
    compile-time, not runtime.
  - **(c) Source trace.** Read `Stillat\BladeParser\...\AttributeCompiler` for `ShorthandDynamicVariable`
    support. Most effort, least repo-specific value.
- **Recommended next step:** option (a). If the add-on won't expose `:$shorthand`, deprioritize.

### FLAG 2 — `flux` prefix in the Antlers `ComponentCompiler`
- **Status:** `$statamicTags = ['statamic','s','flux']`; `flux`/`s`/`statamic` route through the
  `{{ % … }}` lowering (Statamic-tag path), only `x` routes through `%component_proxy`. Full
  `<flux:button>` resolution path not traced.
- **Why it matters:** it contradicts the clean "x = host component, everything else = engine tag"
  mental model; if your engine wants to interoperate with a third-party component lib (Flux/Livewire),
  this is the precedent to copy — or explicitly *not* copy.
- **Verification options:**
  - **(a) Grep + git archaeology (recommended).** `git log -p -S flux -- src/View/Antlers` and search
    for a `flux` tag/handler or namespace registration. Reveals intent and the resolution target fast.
  - **(b) Render trace.** Render `<flux:button>Go</flux:button>` in an Antlers fixture with Flux
    installed and `dd()` the lowered string + final output. Definitive but needs the Flux package.
  - **(c) Declare out of scope.** Generic bridge doesn't need Flux; document as "Statamic-specific
    third-party hook, intentionally not replicated."
- **Recommended next step:** option (a) for a one-paragraph answer; fall back to (c) if the new engine
  has no equivalent component ecosystem.

### FLAG 3 — Two different `<s:…>` compilers depending on host file
- **Status:** confirmed. `<s:…>` in a **Blade** file → `StatamicTagCompiler` (bridge C). `<s:…>` in an
  **Antlers** file → Antlers `ComponentCompiler` (lowers to `{{ % … }}`). Different output, same surface.
- **Why it matters:** scoping which compiler(s) the add-on must implement. If the new engine only ever
  *hosts* templates (never embeds Antlers), you need only one path.
- **Verification options:**
  - **(a) Decide from the add-on's hosting model (recommended).** Answer one question: does the new
    engine host its own files only, or must it also embed/render the other engine's components? That
    determines whether you port one compiler or both.
  - **(b) Confirm by diffing outputs.** Compile the same `<s:collection>` snippet through both
    `StatamicTagCompiler::compile()` and Antlers `ComponentCompiler::compile()` and compare, to make
    the divergence concrete for the implementer.
- **Recommended next step:** option (a) — answer the hosting-model question up front (see §13c step 1);
  it scopes steps 4–8 of the porting blueprint.

**Suggested handoff action:** run FLAG 1(a) and FLAG 2(a) as two ~15-minute spikes before
implementation; resolve FLAG 3 by answering the hosting-model question in the kickoff. None of these
block starting bridges A/B (fully verified, engine-agnostic, reusable verbatim).

---

## Sources
- [Statamic — Blade](https://statamic.dev/blade) · [Antlers](https://statamic.dev/antlers) · [Tags](https://statamic.dev/tags)
- [Laravel — Blade Components](https://laravel.com/docs/blade#components)
- Parser library powering both compilers: `stillat/blade-parser` (`Stillat\BladeParser`)
