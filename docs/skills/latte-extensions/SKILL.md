---
name: latte-extensions
description: Extend the Latte templating engine (v3, PHP) with custom {tags}, n:attributes, filters, functions, compiler passes, providers, extensions, and loaders. Covers Latte compiler internals — lexer/parser, the node AST, PrintContext code generation, context-aware escaping, FilterInfo, NodeTraverser — and the built-in tags as worked examples. Use when creating a Latte Extension, writing a custom tag or filter, transforming or linting the template AST, debugging wrong compiled PHP, or judging what can and cannot be hooked in Latte.
---

# Extending Latte

How to add capabilities to the Latte engine itself. For *writing templates*, use the latte-templates skill; this one is about PHP-side extension: `Latte\Extension`, compiler nodes, passes, filters, functions, loaders.

## What to reach for

| You need | Use | Open |
|---|---|---|
| transform one piped value: `{$x\|thing}` | filter (`addFilter` / `getFilters()`) | [filters-functions-providers.md](references/filters-functions-providers.md) |
| callable in expressions: `{if thing($x)}` | function (`addFunction` / `getFunctions()`) | [filters-functions-providers.md](references/filters-functions-providers.md) |
| a runtime service for compiled code | provider → `$this->global->name` | [filters-functions-providers.md](references/filters-functions-providers.md) |
| new language construct `{tag}` / `n:attr` with a **fixed name** | `getTags()` + a node class | [custom-tags.md](references/custom-tags.md) |
| inspect/rewrite/lint the parsed template | compiler pass + `NodeTraverser` | [compiler-passes.md](references/compiler-passes.md) |
| **dynamic/wildcard tag names** or foreign syntax | loader decorator (source rewrite) — the only pre-parse hook | [compilation-and-nodes.md](references/compilation-and-nodes.md) |
| templates from DB/CMS/anywhere | custom `Latte\Loader` | [compilation-and-nodes.md](references/compilation-and-nodes.md) |
| a starting point to copy for any tag shape | the built-in tag closest to yours | [builtin-tags.md](references/builtin-tags.md) |

Prefer the smallest mechanism: function/filter over tag, tag over pass, pass over loader rewriting. Don't register wrappers for native PHP functions — they're callable in expressions already.

## The Extension surface

`Latte\Extension` subclass, registered with `$latte->addExtension(new MyExtension)`:

| Hook | Consumed | Purpose |
|---|---|---|
| `getTags()` | **each compile** | tag name → parsing function |
| `getPasses()` | **each compile** | AST transforms, ordered via `Extension::order(cb, before:, after:)` |
| `getFilters()` / `getFunctions()` / `getProviders()` | **once, at addExtension()** | render-time callables/services |
| `beforeCompile(Engine)` | each compile, pre-parse | setup; no template access |
| `beforeRender(Template)` | each render | per-render setup |
| `getCacheKey(Engine)` | cache hashing | invalidate compiled templates when your config changes |

Compilation pipeline the hooks plug into: `Loader::getContent → parse (lexer→parser→AST) → passes → generate PHP → cache`. Details, node inventory, and hard limits: [compilation-and-nodes.md](references/compilation-and-nodes.md).

## Custom tag in one minute

```php
// registration:  'greet' => GreetNode::create(...)
class GreetNode extends Latte\Compiler\Nodes\StatementNode
{
    public Latte\Compiler\Nodes\Php\ExpressionNode $name;
    public Latte\Compiler\Nodes\AreaNode $content;

    public static function create(Latte\Compiler\Tag $tag): \Generator   // generator = paired + free n: forms
    {
        $tag->expectArguments();
        $node = $tag->node = new static;
        $node->name = $tag->parser->parseExpression();
        [$node->content] = yield;                       // parse the body up to {/greet}
        return $node;
    }

    public function print(Latte\Compiler\PrintContext $context): string
    {
        return $context->format(
            'echo %escape(%node) %line; %node',
            $this->name, $this->position, $this->content,
        );
    }

    public function &getIterator(): \Generator
    {
        yield $this->name;
        yield $this->content;
    }
}
```

Full anatomy — Tag/TagParser API, generator protocol, `format()` placeholders, output modes, n:attribute variants: [custom-tags.md](references/custom-tags.md).

## Top gotchas

1. `getIterator()` must yield **every child node, by reference** — a missed child is invisible to all compiler passes, including Sandbox security. Leaf idiom: `false && yield;`.
2. Tag names are looked up **exactly**; no wildcards, no unknown-tag fallback, and the lexer/parser are `final` with private registries. Dynamic names must be solved by rewriting the source in a loader decorator — a compiler pass can never rescue an unknown tag.
3. `getFilters()`/`getFunctions()`/`getProviders()` are snapshotted at `addExtension()` time; anything registered into your backing service later is invisible. Tags and passes are re-read on every compile.
4. Editing node/pass logic does **not** invalidate already-compiled templates (only template changes and the config signature do). Clear the compiled-file directory during development, and make `getCacheKey()` reflect config your output depends on.
5. `echo`-ing an expression without `%escape(...)`/`%modify(...)` bypasses Latte's auto-escaping. If you build HTML/JS strings manually, escape with `LR\HtmlHelpers::escapeAttr()` / `LR\Helpers::escapeJs()`, innermost context first.
6. A generator parsing function = paired tag (plus automatic `n:name`, `n:inner-name`, `n:tag-name`); a plain-return function = standalone only. Handle `$tag->void` (self-closed `{tag /}` and void elements) before yielding.
7. Intermediate tags (`{else}`, `{case}`) are not registered tags — they exist only as names in your `yield [...]` list; read their args from the returned `$nextTag->parser`.
8. Validate everything in `create()` (`expectArguments()`, `closestTag()` nesting checks, rejecting `isNAttribute()`) so misuse throws `CompileException` at compile time, not broken PHP at render time.
9. Generated-code temp vars: use `$__name_` . `$context->generateId()`; both `$__*` and `$ʟ_*` are reserved prefixes templates cannot declare.
10. Filters on non-text blocks must be contextual (`FilterInfo` first param) — classic filters throw "incompatible content type" there. Wrap captured output in `LR\Html` only when the escaper state was HTML text.
11. Name collisions (tags/filters/functions/providers) resolve as **last extension wins** — order `addExtension()` calls deliberately.

## Probing recipes

```php
$latte->parse($src);                                              // parse only — test CompileExceptions, inspect the AST
$latte->setLoader(new Latte\Loaders\StringLoader(['t' => $src]));
echo $latte->compile('t');                                        // read the generated PHP — the ground truth
$latte->invokeFilter('name', [$value]);                           // test filters without a template
```
