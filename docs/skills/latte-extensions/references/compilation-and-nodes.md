# Compilation pipeline, node types, and extensibility limits

## Contents

- [The pipeline](#the-pipeline)
- [Lexer and parser internals](#lexer-and-parser-internals)
- [What you cannot hook — and the loader workaround](#what-you-cannot-hook--and-the-loader-workaround)
- [Node type inventory](#node-type-inventory)
- [The getIterator() contract](#the-getiterator-contract)
- [Escaping and content types](#escaping-and-content-types)
- [Custom loaders](#custom-loaders)
- [Probing recipes](#probing-recipes)

## The pipeline

`Engine::compile($name)` runs three phases (`src/Latte/Engine.php`):

```
(1) $source = $loader->getContent($name)      // Loader — the only pre-parse hook
(2) $node   = $engine->parse($source)         // lexer → parser → AST (TemplateNode)
(3) $engine->applyPasses($node)               // compiler passes mutate the AST
(4) $code   = $engine->generate($node, $name) // AST → PHP class source
```

- `parse()` calls each extension's `beforeCompile($engine)` and collects `getTags()` **per compile**.
- `applyPasses()` merges all extensions' `getPasses()`, orders them with `Helpers::sortBeforeAfter()` (topological, supports `Extension::order($cb, before:, after:)` with `'*'`), then runs each on the root `TemplateNode`.
- `generate()` uses `TemplateGenerator` to build `final class Template_xxx extends Latte\Runtime\Template` with a `main()` method, a `prepare()` method (head section), and one method per block. Compiler-generated locals use the `$ʟ_` prefix (reserved: templates cannot declare `$ʟ_*` variables).
- The compiled-file cache key includes a configuration signature; every extension's `getCacheKey($engine)` feeds it, so changing extension config invalidates caches. **Changing pass/node logic without changing the signature does not** — delete compiled files during development.

## Lexer and parser internals

Classes in `src/Latte/Compiler/`:

| Class | Role |
|---|---|
| `TemplateLexer` | tokenizes source; state machine (`StatePlain`, `StateLatteTag`, `StateHtmlText`, `StateHtmlTag`, `StateHtmlQuotedValue`, `StateHtmlRawText`, ...); `setSyntax()`/`pushSyntax()`/`popSyntax()` switch `{...}` delimiters (how `{syntax off}` works) |
| `TagLexer` | tokenizes the PHP-like expression text *inside* a tag into `Php_*` tokens |
| `Token` | `readonly` value: `->type` (int constants: `Token::Text`, `Latte_TagOpen`, `Html_Name`, big `Php_*` block), `->text`, `->position`; `is(...$kind)` |
| `TokenStream` | lazy buffer: `peek($offset)`, `consume(...$kind)` (throws on mismatch), `tryConsume(...$kind)`, `is(...$kind)` |
| `TemplateParser` | builds the AST; dispatches each `{tag}` to its registered parsing function; tracks `$blocks`, `$inHead`; HTML handled by `TemplateParserHtml` |

Facts that shape what's possible:

- Tag names are matched by the lexer as one token: `[a-z]\w*(?:[.:-]\w+)*` — dotted/coloned names like `foo:bar.baz` arrive as a **single name**, looked up **exactly** in the registered tag map. No wildcards, no fallback handler for unknown tags: unknown name = `CompileException`.
- Tag arguments are the raw token run after the name; they become the tag's `TagParser` (`$tag->parser`), whose grammar is PHP-like. Foreign syntaxes must fit it or be parsed manually from `$tag->parser->stream`/`->text`.
- A tag registered with a **generator** parsing function is treated as paired *and* automatically gains `n:name`, `n:inner-name`, and `n:tag-name` attribute forms; a plain-return function is a standalone tag only.

## What you cannot hook — and the loader workaround

`TemplateLexer`, `TagLexer`, and `TemplateParser` are `final`, their registries private, and `Engine::parse()` instantiates them directly — there is **no API for custom lexing, token rewriting, or dynamic/wildcard tag names**. Compiler passes run only *after* a successful parse, so they can never rescue an unknown tag.

The single pre-parse seam is the **Loader** (pipeline step 1). For dynamic tag names or any source-level syntax extension, decorate the loader and rewrite the source into registered tags:

```php
final class RewritingLoader implements Latte\Loader
{
    public function __construct(private Latte\Loader $inner) {}

    public function getContent(string $name): string
    {
        return MySyntax::rewrite($this->inner->getContent($name)); // pure function — unit-testable
    }

    // delegate getReferredName() and getUniqueId() to $this->inner
}

$latte->setLoader(new RewritingLoader($latte->getLoader()));
```

Caveats: regex rewriting is blind to `{* comments *}` and raw `<script>`/`<style>` text — keep patterns narrow; it runs on every cache-missed compile, keep it cheap.

## Node type inventory

Everything in the AST extends `Latte\Compiler\Node` (`?Position $position`, abstract `print(PrintContext): string`, abstract `&getIterator(): \Generator`). Namespace `Latte\Compiler\Nodes`:

| Node | Purpose |
|---|---|
| `TemplateNode` | root; `FragmentNode $head` (declarations), `FragmentNode $main`, `string $contentType` |
| `AreaNode` | abstract base for anything producing output; extend directly only for passive content |
| `StatementNode` | `AreaNode` + `$tagRanges` — **the base class for custom tags** |
| `FragmentNode` | ordered `$children` list; `append()`, `simplify()` |
| `TextNode` | literal text (`$content`); leaf |
| `NopNode` | prints nothing; placeholder for removed content |
| `AuxiliaryNode` | wraps a `\Closure $print` + child `$nodes`; emit arbitrary PHP while children stay traversable — also hides the emitted code itself from passes (Sandbox) |
| `Html\ElementNode` | HTML element: `$name`, `$attributes`, `$content`, `$nAttributes`, `$parent`, `isRawText()` |
| `Html\AttributeNode` | one attribute: `$name`, `$value`, `$quote` |
| `Php\ExpressionNode` | abstract base for expressions (from `parseExpression()`) |
| `Php\Scalar\{String,Integer,Float,Boolean,Null}Node` | literals (`->value`); leaves |
| `Php\Expression\ArrayNode` | `$items` (`ArrayItemNode[]`); returned by `parseArguments()`; `toArguments()` powers `%args` |
| `Php\Expression\VariableNode` | `$name` (string or nested expression) |
| `Php\Expression\{FunctionCall,MethodCall,StaticMethodCall,PropertyFetch,ArrayAccess,BinaryOp,UnaryOp,Ternary,Assign,Closure,Match,New,...}Node` | full PHP expression tree |
| `Php\{Identifier,Name,ArrayItem,Argument,Parameter}Node` | support nodes; note named-arg keys are `IdentifierNode`, array `=>` keys are `StringNode` |
| `Php\ModifierNode` / `Php\FilterNode` | a `\|filter:args\|...` chain and its links; `$escape` flag = no `\|noescape` |

There are two `AuxiliaryNode` classes: `Nodes\AuxiliaryNode` (statement/area position) and `Nodes\Php\Expression\AuxiliaryNode` (expression position).

## The getIterator() contract

`NodeTraverser` iterates `foreach ($node as &$child)` and **assigns back** the callback's replacement. Therefore in every node you write:

- yield **every** child node — a child you don't yield is invisible to all compiler passes (including Sandbox security checks);
- yield **by reference** (the method signature is `&getIterator()`), or replacement/removal silently fails;
- leaf nodes use the idiom `false && yield;`;
- keep child-node properties `public` so passes can rewrite them.

## Escaping and content types

Escaping is decided at **compile time** by `Latte\Compiler\Escaper`, driven by the node printers (`Html\ElementNode::print` etc. call `$context->beginEscape()->enterHtmlText(...)` and restore afterward). States combine `Latte\ContentType` (`Html`, `Xml`, `Text`, `JavaScript`, `Css`, `ICal`) with HTML sub-states (`html/tag`, `html/attr`, `html/comment`, `html/raw`) and sub-types (an `onclick` attr escapes as JS inside attr). In a tag's `print()`:

- `%escape(expr)` emits the correct escaping call for the current context;
- `%modify(expr)` applies the tag's parsed filter chain *and* contextual escaping (honoring `|noescape`);
- if you hand-build HTML/JS output, escaping is your job — use the runtime helpers (`LR\HtmlHelpers::escapeAttr()`, `LR\Helpers::escapeJs()`; `LR` = `Latte\Runtime`, aliased in generated code).

Content-type-aware ("contextual") filter mechanics live in [filters-functions-providers.md](filters-functions-providers.md).

## Custom loaders

`Latte\Loader` interface (built-ins: `Loaders\FileLoader`, `Loaders\StringLoader`):

| Method | Contract |
|---|---|
| `getContent(string $name): string` | return source or throw `Latte\TemplateNotFoundException` |
| `getReferredName(string $name, string $referringName): string` | resolve `{include}`/`{layout}` names relative to the referring template |
| `getUniqueId(string $name): string` | unique id used in the compiled-cache filename |

## Probing recipes

```php
$latte->parse($src);                       // parse only — surfaces CompileExceptions, returns the AST
$latte->setLoader(new Latte\Loaders\StringLoader(['t' => $src]));
echo $latte->compile('t');                 // inspect the generated PHP
$latte->enablePhpLinter('/usr/bin/php');   // validate generated PHP after compile
```
