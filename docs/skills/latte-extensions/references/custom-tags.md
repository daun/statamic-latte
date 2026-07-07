# Custom tags and n:attributes

## Contents

- [Anatomy: parsing function + node](#anatomy-parsing-function--node)
- [The Tag object](#the-tag-object)
- [Parsing arguments: TagParser](#parsing-arguments-tagparser)
- [Paired tags: the generator protocol](#paired-tags-the-generator-protocol)
- [print() and format() placeholders](#print-and-format-placeholders)
- [Escaping in print()](#escaping-in-print)
- [Output modes](#output-modes)
- [n:attributes](#nattributes)
- [Errors and validation](#errors-and-validation)

## Anatomy: parsing function + node

A tag = a **parsing function** (runs at compile time, consumes the tag's tokens, returns a node) + a **node class** (lives in the AST, prints PHP). Register in `Extension::getTags()`:

```php
public function getTags(): array
{
    return [
        'datetime'  => DatetimeNode::create(...),   // {datetime}
        'n:confirm' => ConfirmNode::create(...),    // attribute-only tag
    ];
}
```

The complete pattern — a standalone tag with one optional argument:

```php
use Latte\Compiler\{Nodes\StatementNode, Nodes\Php\ExpressionNode, Nodes\Php\Scalar\StringNode, PrintContext, Tag};

class DatetimeNode extends StatementNode
{
    public ?ExpressionNode $format = null;    // children must be public for passes

    public static function create(Tag $tag): static
    {
        $tag->outputMode = $tag::OutputKeepIndentation;   // it prints inline output
        $node = $tag->node = new static;                  // back-reference: children/passes find it mid-parse
        if (!$tag->parser->isEnd()) {
            $node->format = $tag->parser->parseExpression();
        }
        return $node;
    }

    public function print(PrintContext $context): string
    {
        return $context->format(
            'echo %escape(date(%node)) %line;',
            $this->format ?? new StringNode('Y-m-d H:i:s'),
            $this->position,
        );
    }

    public function &getIterator(): \Generator
    {
        if ($this->format) {
            yield $this->format;
        }
    }
}
```

Rules that make or break it:

- Extend `StatementNode`. A parsing function that **returns a node** makes a standalone tag; one that **is a generator** makes a paired tag (and automatically gains `n:` attribute forms).
- `getIterator()` must yield **every child node, by reference** (`&getIterator`). Miss one and compiler passes — including Sandbox security — silently skip it. No children: `false && yield;`.
- Do compile-time work in `create()` so misuse fails at compile, not render.

## The Tag object

`Latte\Compiler\Tag` — what `create()` receives:

| Member | Meaning |
|---|---|
| `$tag->parser` | the `TagParser` over the tag's arguments |
| `$tag->name` | tag name (`'='` for `{=expr}`) |
| `$tag->void` | `{tag /}` self-closed, or n:attribute on a void element — skip the body |
| `$tag->position` | source `Position` — store on your node, pass to exceptions |
| `$tag->node` | assign your node here immediately (`$tag->node = new static`) |
| `$tag->outputMode` | whitespace handling, see [Output modes](#output-modes) |
| `$tag->isNAttribute()` / `$tag->prefix` | invoked as `n:...`? which variant (`Tag::PrefixNone/PrefixInner/PrefixTag`) |
| `$tag->htmlElement` | enclosing `Html\ElementNode` (the element itself for n:attributes) |
| `$tag->closestTag([FooNode::class], ?$cond)` | nearest open ancestor tag whose node matches — nesting validation, context lookup |
| `$tag->expectArguments()` | throw if the tag has no arguments |
| `$tag->getNotation(bool $withArgs = false)` | `'{tag}'` / `'n:tag'` for error messages |
| `$tag->isInHead()` | still in the template head (declaration section) |
| `$tag->replaceNAttribute($node)` | swap this n:attribute's placeholder inside the element's attribute list |

## Parsing arguments: TagParser

`$tag->parser` methods, from coarse to fine:

| Call | Returns | Use for |
|---|---|---|
| `parseArguments()` | `ArrayNode` | comma-separated args, named (`key: v` → `IdentifierNode` keys) and positional; print with `%args` |
| `parseExpression()` | `ExpressionNode` | one PHP-like expression |
| `parseUnquotedStringOrExpression()` | `ExpressionNode` | names that may be bare words (file/block names) — dynamic names come free |
| `parseModifier()` | `ModifierNode` | the trailing `\|filter:x\|...` chain |
| `parseType()` | `?SuperiorTypeNode` | PHP type declarations |
| `isEnd()` | bool | any arguments left? |
| `->text` | string | the raw argument text (how `{php}` grabs everything verbatim) |
| `->stream` | `TokenStream` | token-level control: `peek()`, `is(...)`, `consume(...)` (throws), `tryConsume(...)` |
| `tryConsumeTokenBeforeUnquotedString(...$kind)` | `?Token` | consume a keyword only when followed by more input (how `{include}` detects `block`/`file`) |

The grammar is PHP-like and rejects foreign syntax. For a custom mini-syntax, either work the token stream directly, or take `->text` and drain the stream (`while (!$tag->parser->stream->consume()->isEnd());`) then parse it yourself. Array `key => v` keys parse as `StringNode`, named-arg `key: v` keys as `IdentifierNode` — handle both when reading `ArrayNode->items`.

## Paired tags: the generator protocol

Make `create()` a generator; each `yield` hands control back to the parser, which parses body content and sends it back:

```php
/** @return \Generator<int, ?list<string>, array{AreaNode, ?Tag}, static> */
public static function create(Tag $tag): \Generator
{
    $node = $tag->node = new static;
    $node->subject = $tag->parser->parseExpression();

    if ($tag->void) {                    // {tag /} or n:tag on a void element
        $node->content = new NopNode;
        return $node;
    }

    [$node->content, $nextTag] = yield ['else'];   // stop at {else} or {/tag}
    if ($nextTag?->name === 'else') {
        [$node->else, $endTag] = yield;            // rest, up to {/tag}
    }
    return $node;
}
```

- `yield` with no value parses to the closing `{/tag}`; `yield ['else', 'case']` also stops at those intermediate tags. `$nextTag` tells you which one ended the segment (`null`/the closing tag otherwise).
- Loop yields to collect repeating segments (how `{switch}` gathers `{case}`s); `yield from self::create($nextTag, $parser)` chains recursively (how `{elseif}` builds a nested `IfNode`).
- Intermediate tags like `{else}` are **not** registered tags — they exist only because your yield names them. Parse their arguments from `$nextTag->parser`.
- Always `return` the node. On the self-closing form the parser sends an empty `FragmentNode` immediately.

## print() and format() placeholders

`$context->format($mask, ...$args)` builds the PHP — never concatenate PHP by hand. Placeholders consume args left-to-right:

| Mask | Arg | Emits |
|---|---|---|
| `%node` | `?Node` | the child's printed PHP (auto-parenthesized when precedence requires) |
| `%dump` | any PHP value | the value as a PHP literal |
| `%raw` | string | verbatim PHP you already built |
| `%args` | `ArrayNode` | a call's argument list |
| `%line` | `?Position` | `/* pos L:C */` marker — include it once per statement group |
| `%escape(expr)` | — | expr wrapped in the correct escaping call for the current context |
| `%modify(expr)` | `ModifierNode` | expr piped through the tag's filter chain + contextual escaping |
| `%modifyContent(expr)` | `ModifierNode` | filter chain in content mode (block-level, `FilterInfo`-aware) |

`%0.node`, `%2.raw` re-reference a positional arg; a `?` suffix (`%node?`) collapses when the arg is empty/`[]`/`null`.

Runtime temp variables: prefix with `$__` plus `$context->generateId()` for nesting safety (`$__count_3`) — both `$__*` and `$ʟ_*` (core's own prefix) are reserved names templates cannot declare. Reference runtime classes by FQCN, or `LR\` for `Latte\Runtime` (aliased in generated files).

Capture-the-body pattern (filter the rendered output — see `CaptureNode`/`TranslateNode`):

```php
ob_start(fn() => '') %line;
try { %node } finally { $ʟ_tmp = ob_get_clean(); }
$ʟ_fi = new LR\FilterInfo(%dump); echo %modifyContent($ʟ_tmp);
```

## Escaping in print()

Anti-pattern — printing user expressions raw:

```php
return $context->format('echo date(%node);', $this->format);          // WRONG: bypasses auto-escaping
return $context->format('echo %escape(date(%node));', $this->format); // right: context-aware
```

If you assemble HTML/JS strings yourself, escape explicitly with the runtime helpers, innermost context first (model: the official `n:confirm` example):

```php
return $context->format(<<<'XX'
    echo ' onclick="', LR\HtmlHelpers::escapeAttr('return confirm(' . LR\Helpers::escapeJs(%node) . ')'), '"' %line;
    XX, $this->message, $this->position);
```

Only wrap captured output in `new LR\Html(...)` when the escaper state was HTML text (`$context->getEscaper()->getState() === Escaper::HtmlText`) — marking attribute/JS content HTML-safe is an XSS bug.

## Output modes

Set `$tag->outputMode` in `create()`:

| Mode | For | Whitespace effect |
|---|---|---|
| `Tag::OutputNone` (default) | declaration tags (`{var}`-like) | indentation removed; tag can stay in the template head |
| `Tag::OutputRemoveIndentation` | tags producing statements | removes leading indentation + one trailing newline |
| `Tag::OutputKeepIndentation` | tags printing inline output | preserves surrounding whitespace; ends the head section |

## n:attributes

- Any **generator** (paired) tag automatically works as `n:name` (wraps the whole element), `n:inner-name` (wraps only the content), and `n:tag-name` (wraps only the open/close tags). You write no extra code — the body you receive from `yield` *is* the element/content.
- Detect and branch: `$tag->isNAttribute()`, `$tag->prefix` (`Tag::PrefixNone/PrefixInner/PrefixTag`). Reject unsupported forms early (`{embed}`, `{switch}` throw on n: usage).
- **Attribute-only tags** are registered with the literal prefix in the key: `'n:confirm' => ConfirmNode::create(...)`. A non-generator n: tag returns a node that prints *in the attribute list position* (emit ` attr="..."` with a leading space); a generator one wraps the element.
- On the element: `$tag->htmlElement` gives the `ElementNode` (`getAttribute('class')` to detect clashes, `->attributes->children` to inspect, `->dynamicTag` for tag-name rewriting). Built-in models: `NClassNode` (inline attr echo), `NAttrNode` (dynamic attrs), `NTagNode` (mutates the element in `create()`, prints nothing), `IfContentNode` (buffers and conditionally suppresses the element), `NElseNode` (a marker spliced into a *sibling* node by a compiler pass).

## Errors and validation

- Throw `Latte\CompileException($message, $tag->position)` from `create()` for misuse: missing args (`$tag->expectArguments()`), wrong nesting (`$tag->closestTag(...)` returned null), unsupported n: form, non-writable assignment target (`$expr->isWritable()`).
- Use `$tag->getNotation()` in messages so `{tag}` vs `n:tag` reads correctly.
- Security-motivated rejections: `Latte\SecurityViolationException`.
- Verify output by compiling a probe template and reading the PHP: `$latte->setLoader(new StringLoader(['t' => '...'])); echo $latte->compile('t');`.
