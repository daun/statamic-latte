# Built-in tags as worked examples

All built-in tag nodes live in `src/Latte/Essential/Nodes/`, registered in `CoreExtension::getTags()` (`src/Latte/Essential/CoreExtension.php`). When building a custom tag, start from the built-in closest to your shape.

## Contents

- [Copy-from table](#copy-from-table)
- [The instructive dissections](#the-instructive-dissections)
- [Block machinery: what reusing it buys](#block-machinery-what-reusing-it-buys)
- [Shipped extensions worth copying](#shipped-extensions-worth-copying)

## Copy-from table

| You want a tag that... | Copy | Teaches |
|---|---|---|
| emits a fixed snippet, no args | `'l' => fn(Tag $tag) => new TextNode('{', $tag->position)` | a tag handler is just a callable returning an `AreaNode` |
| takes one optional expression | `DumpNode` | `isEnd()` check, `%node`/`%dump`/`%line` |
| executes an expression, no output | `DoNode` | reserved-keyword rejection via `throwReservedKeywordException()` |
| swallows its arguments as raw text | `RawPhpNode` | `->text` + stream draining, `%raw` |
| assigns variables | `VarNode` | writable-target validation, by-ref `getIterator` + `Helpers::removeNulls`, `AuxiliaryNode` for ad-hoc codegen |
| has `{else}`/branches | `IfNode` | multi-yield, `$nextTag->name` dispatch, recursion via `yield from` for `{elseif}` |
| loops with `{else}` and `$iterator` | `ForeachNode` | `$tag->void` handling, conditional `CachingIterator` (it greps the compiled body for `$iterator`), scoped-variable backup, node shipping its own pass |
| has repeating sub-tags (`{case}`) | `SwitchNode` | `yield ['case','default']` in a loop; intermediate-tag args via `$nextTag->parser` |
| reacts to the enclosing loop | `FirstLastSepNode` (implicit `$iterator` in scope) vs `JumpNode` (walks `closestTag` ancestors, validates nesting) | two ways to bind to context |
| buffers its body into a variable | `CaptureNode` | ob_start/finally capture, `LR\Html` wrapping only in HtmlText state, `%modifyContent` |
| transforms its body content | `TranslateNode` | `FilterInfo` + `%modifyContent`, compile-time fast path via `NodeHelpers::toText()`, prepending a filter to the modifier chain |
| survives runtime failure | `TryNode` + `RollbackNode` | `ob_clean()` on catch, provider-based exception handler, control-flow via internal exception, `generateId()` keys for nesting |
| defines reusable/overridable content | `BlockNode`/`DefineNode` | the Block/layer machinery below |
| is attribute-only (n:) | `NClassNode` (inline echo), `NAttrNode` (dynamic attrs), `NTagNode` (mutates element in `create()`, prints nothing), `IfContentNode` (conditionally suppresses its element), `NElseNode` (spliced into a sibling by a pass) | element interaction patterns |
| must sit in the template head | `ContentTypeNode` | `isInHead()` gate, `beginEscape()->enterContentType()` |

Tags that **reject** the n: form (`CompileException` in `create()`): `{embed}`, `{switch}`, `{syntax}` — copy their guard when your tag can't wrap an element.

## The instructive dissections

**IfNode — multi-branch parsing.** First yield names all legal interruptions; the returned `$nextTag` drives a state machine:

```php
[$node->then, $nextTag] = yield $node->capture ? ['else'] : ['else', 'elseif', 'elseifset'];
if ($nextTag?->name === 'else') {
    [$node->else] = yield;
} elseif ($nextTag) {                                  // elseif / elseifset
    $node->else = yield from self::create($nextTag, $parser);   // nested IfNode, recursively parsed
}
```

Also branches on `$tag->isNAttribute()` — the argument-less capturing `{if}...{/if $cond}` form is disabled for `n:if`.

**ForeachNode — pragmatic codegen.** It compiles the body first, then decides whether to allocate a `CachingIterator` by regex-scanning the compiled string for `$iterator` — compile-time introspection of your own output is legitimate. `|noiterator`/`|nocheck` are consumed from `parseModifier()->filters`, any other filter throws.

**TranslateNode — the content-transformer template.** Parse args + modifier, capture body, then: if `NodeHelpers::toText()` yields static text and the translator resolves at compile time, replace the body with a translated `TextNode` (zero runtime cost); else prepend a `translate` `FilterNode` to the modifier and emit the ob_start + `%modifyContent` capture. Any "run my service over the rendered body" tag is this shape.

**NElseNode — sibling rewriting.** The n:else attribute parses to a marker node whose `print()` throws; a compiler pass (`NElseNode::processPass`) later walks fragment children and splices the marker into the *preceding* `IfNode`/`ForeachNode`/... `->else` slot. Pattern for any attribute that modifies a neighbor rather than its own element.

**The noescape idiom** (used by every tag with a modifier):

```php
$node->modifier = $tag->parser->parseModifier();
$node->modifier->escape = !$node->modifier->removeFilter('noescape');
```

## Block machinery: what reusing it buys

`{block}`-family bodies compile into **methods** on the generated template class, keyed through `Latte\Compiler\Block` (name + layer + escaping) and layers (`Template::LayerTop`, `LayerLocal`, `LayerSnippet`, integer layers per `{embed}`). The flow inside a block-like node's `print()`:

```php
$this->block = new Block($nameExprNode, $layer, $tag);
$context->addBlock($this->block);                     // 1. register — assigns the method name
$this->block->content = $this->content->print($context);  // 2. THEN compile the body (nested blocks register correctly)
return $context->format('$this->renderBlock(%node, get_defined_vars()) %line;', ...);
```

Reusing `Block` + `PrintContext::addBlock()` + `Template::renderBlock()` buys: named overridable content, `{include parent/this}` resolution (`$tag->closestTag([BlockNode::class, DefineNode::class])`), parameters (`DefineNode`), dynamic block names, and cross-template rendering (`createTemplate(...)->renderToContentType(...)` — see `IncludeFileNode`/`EmbedNode`). Don't reinvent any of this for "definable/overridable region" tags. Registration during parse goes through `TemplateParser::$blocks`/`$blockLayer` (`IncludeBlockNode` keeps `&$parser->blocks` by reference).

## Shipped extensions worth copying

| Extension | Overrides | Model for |
|---|---|---|
| `Essential\RawPhpExtension` | `getTags` (one line) | minimal single-tag extension |
| `Essential\TranslatorExtension` | `getTags` + `getFilters` + `getCacheKey` | tag/filter combo backed by a service; `getCacheKey` returns the translation key so compile-time translation invalidates correctly |
| `Bridges\Tracy\TracyExtension` | `beforeRender` only | pure runtime/observability hook — no tags at all |
| `Sandbox\SandboxExtension` | `beforeCompile`, `getTags`, `getPasses` (`before: '*'`), `beforeRender`, `getCacheKey` | AST-rewriting pass + runtime provider (`$this->global->sandbox`) enforcing a policy |

`CoreExtension` itself demonstrates: closure tags (`'l' => fn...`), one node serving several tags (`FirstLastSepNode`, `VarNode` for `{var}`/`{default}`), a parse-time dispatcher choosing between nodes (`includeSplitter` → `IncludeBlockNode` vs `IncludeFileNode`), and `getCacheKey()` returning `array_keys($engine->getFunctions())`.
