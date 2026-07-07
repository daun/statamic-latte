# The Extension surface

`Latte\Extension` is the base class. Override any subset. Register an instance
with `$engine->addExtension(new MyExtension($engine))`.

| Hook | Runs | Purpose |
| --- | --- | --- |
| `getTags(): array` | during `parse()` | register `{tag}` / `n:attr` parsers by **exact name** |
| `getPasses(): array` | **after** parse, on the AST | transform / lint / validate the node tree |
| `getFilters(): array` | render | `\|filter` |
| `getFunctions(): array` | render | `func()` inside expressions |
| `getProviders(): array` | render | runtime services, reachable as `$this->global->name` |
| `beforeCompile(Engine)` | start of `parse()`, per extension | one-time setup; **no template access** |
| `beforeRender(Template)` | each render | per-render setup |
| `getCacheKey(Engine): mixed` | cache hashing | invalidate compiled cache when your config changes |

## getTags() — custom tags

```php
public function getTags(): array {
    return [
        'greet'   => [GreetNode::class, 'create'],   // {greet}
        'n:greet' => [GreetNode::class, 'create'],   // n:greet attribute (NPrefix = "n:")
    ];
}
```

- The value is `callable(Tag, TemplateParser): Node|\Generator|void`.
- Keys are **exact names**. No wildcards / patterns / fallbacks (see PARSING-INTERNALS.md).
- A generator `create()` ⇒ **paired** tag; a plain return ⇒ **void/self-closing** only.
- For `n:` attributes Latte auto-derives inner/outer variants for generator tags.

### Node anatomy

```php
final class GreetNode extends \Latte\Compiler\Nodes\StatementNode {
    public \Latte\Compiler\Nodes\Php\Expression\ArrayNode $args;
    public \Latte\Compiler\Nodes\AreaNode $content;
    public bool $selfClosing = false;

    // Parser: build the node, optionally yield to capture the body.
    public static function create(\Latte\Compiler\Tag $tag): \Generator {
        $node = $tag->node = new self;
        $node->args = $tag->parser->parseArguments();
        [$node->content, $endTag] = yield;          // returns [AreaNode $body, ?Tag $endTag]
        $node->selfClosing = ! $endTag || $endTag === $tag;
        return $node;
    }

    // Printer: emit PHP source for the runtime.
    public function print(\Latte\Compiler\PrintContext $c): string {
        return $c->format('echo %dump; %node %line', 'Hi ', $this->content, $this->position);
    }

    // Iterator: expose child nodes so passes can traverse and printing works.
    public function &getIterator(): \Generator {
        yield $this->args;
        yield $this->content;
    }
}
```

`StatementNode` ⇒ produces statements (most tags). For an expression-producing
tag extend an expression node instead.

### PrintContext::format() placeholders

| Token | Meaning |
| --- | --- |
| `%dump` | `var_export` a PHP value as a literal |
| `%node` | print a child `Node` inline |
| `%raw` | inject a raw PHP string you built |
| `%args` | print an `ArrayNode` as call arguments |
| `%line` | emit a `/* line N */` position comment |

## Argument parsing

- Raw text of the tag args: `$tag->parser->text` (string). The full parser is a
  `TagParser` exposing `parseArguments()`, `parseExpression()`, `parseModifier()`.
- Named arg `key: value` ⇒ parsed key is an **`IdentifierNode`**.
  Array fat-arrow `key => value` ⇒ parsed key is a **`StringNode`**. Handle both.
- **Foreign syntax workaround.** Latte's grammar rejects non-PHP arg syntax
  (e.g. Statamic's `title:contains: foo`). Pattern that works:
  1. Mask the offending chars to a placeholder in `$tag->parser->text`.
  2. Tokenize + `parseArguments()` the masked text yourself
     (`new TagParser((new TagLexer)->tokenize($masked))`).
  3. Restore the placeholder on the parsed `IdentifierNode`/`StringNode` keys.
  4. **Drain the original stream** so Latte sees it consumed:
     `while (! $tag->parser->isEnd()) $tag->parser->stream->consume();`
  Skip masking inside quoted strings.

## getPasses() — AST transforms

```php
public function getPasses(): array {
    return ['my-pass' => function (\Latte\Compiler\Nodes\TemplateNode $node): void {
        (new \Latte\Compiler\NodeTraverser)->traverse($node, function ($n) {
            // inspect/replace nodes; rename, wrap, validate, collect
            return $n;
        });
    }];
}
```

Use passes to rewrite/validate the tree. By the time a pass runs the parse already succeeded.

## Filters, functions, providers

```php
public function getFilters(): array   { return ['shout' => fn (string $s) => strtoupper($s)]; }
public function getFunctions(): array { return ['nowYear' => fn () => date('Y')]; }
public function getProviders(): array { return ['clock' => $myClockService]; } // $this->global->clock
```

Filters/functions run at render time and receive evaluated values. Providers
expose services on the runtime `$this->global`.

## Wiring into a framework

- Engine is usually a DI singleton. Add extensions where it's built/booted.
- Set the loader with `$engine->setLoader($loader)` (decorate it for source
  preprocessing — see PARSING-INTERNALS.md).
- Implement `getCacheKey()` if your extension's behavior depends on external
  config, so stale compiled templates get rebuilt.
