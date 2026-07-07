# Compiler passes

A compiler pass is a callable `function (Latte\Compiler\Nodes\TemplateNode $node): void` that inspects or mutates the AST after parsing, before code generation. Register via `Extension::getPasses()`; order across all extensions with `Extension::order()`:

```php
public function getPasses(): array
{
    return [
        'my-lint'    => $this->lintPass(...),
        'my-rewrite' => Latte\Extension::order($this->rewritePass(...), before: '*'),  // also after: 'name'
    ];
}
```

Passes from all extensions share one namespace and are topologically sorted (`before`/`after` may reference other extensions' pass names; `'*'` = all). By the time a pass runs, parsing already succeeded — a pass can never handle unknown tags (see [compilation-and-nodes.md](compilation-and-nodes.md) for that limit).

## NodeTraverser

```php
use Latte\Compiler\{Node, NodeTraverser};

(new NodeTraverser)->traverse(
    $templateNode,
    enter: fn(Node $node) => ...,   // before children — gather info, prune subtrees
    leave: fn(Node $node) => ...,   // after children — replace/remove here
);
```

Callback return values:

| Return | Effect |
|---|---|
| `null` / nothing | keep node, continue |
| a `Node` | **replace** the current node (copy `$node->position` onto the replacement) |
| `NodeTraverser::RemoveNode` | delete the node (only valid where the parent tolerates it — use with care) |
| `NodeTraverser::DontTraverseChildren` | from `enter`: skip this subtree |
| `NodeTraverser::StopTraversal` | halt the whole traversal (powers `NodeHelpers::findFirst`) |

Replacement/removal work only because parents yield children **by reference** from `getIterator()` — a node that fails that contract is silently untouchable.

## NodeHelpers

`Latte\Compiler\NodeHelpers` static utilities — reach for these before hand-rolling traversal:

| Helper | Use |
|---|---|
| `find($node, $filter): array` | all matching descendant nodes |
| `findFirst($node, $filter): ?Node` | first match |
| `clone($node): Node` | deep copy of a subtree |
| `toValue($exprNode, constants: true): mixed` | compile-time evaluate a literal/const expression; throws `InvalidArgumentException` if not static |
| `toText(?$node): ?string` | extract plain text from `TextNode`/`FragmentNode`/`NopNode` trees, else `null` |

`toValue()`/`toText()` enable ahead-of-time optimization: e.g. `{translate}` translates at compile time when the body is static text, falling back to a runtime filter otherwise.

## Pass templates

**Validation pass** (analysis, throws) — match a node shape, throw with position:

```php
use Latte\Compiler\Nodes\Php;

function forbidDangerousCalls(Latte\Compiler\Nodes\TemplateNode $node): void
{
    (new NodeTraverser)->traverse($node, enter: function (Node $node) {
        if ($node instanceof Php\Expression\FunctionCallNode
            && $node->name instanceof Php\NameNode
            && in_array(strtolower((string) $node->name), ['exec', 'shell_exec'], true)
        ) {
            throw new Latte\SecurityViolationException("Function {$node->name}() is not allowed.", $node->position);
        }
    });
}
```

**Rewrite pass** (modification, returns a replacement) — the shipped `customFunctionsPass` shape:

```php
leave: function (Node $node) {
    if ($node instanceof Php\Expression\FunctionCallNode && /* name matches */) {
        $new = new MyCallNode(...);
        $new->position = $node->position;
        return $new;
    }
}
```

**HTML-manipulation pass** — match `Nodes\Html\ElementNode`, inspect `$node->getAttribute('x')` / `$node->attributes->children`, append `new Html\AttributeNode(name: new TextNode('loading'), value: new TextNode('lazy'), quote: '"')`.

Shipped passes to copy from (`src/Latte/Essential/Passes.php` + node-hosted passes): `forbiddenVariablesPass`, `checkUrlsPass`, `customFunctionsPass`, `scriptTagQuotesPass`, `NElseNode::processPass` (an n:attribute that splices itself into a *sibling* node's `->else` — the pattern for cross-node rewrites), `ForeachNode::overwrittenVariablesPass` (a node shipping its own pass).

The heavyweight reference is the **Sandbox** (`src/Latte/Sandbox/SandboxExtension.php`): a `before: '*'` pass that forbids constructs and *replaces* call/property nodes with wrapper nodes routing through a runtime policy checker provider — the model for "AST rewrite + runtime provider" extensions.

## Rules of thumb

- Throw `Latte\CompileException` (or `SecurityViolationException`) with the node's `Position` — never let bad input reach code generation.
- Passes run once per compile, and **not again** until the template file or the engine configuration signature changes. Editing pass logic alone doesn't invalidate compiled templates: clear the temp directory, or make `getCacheKey()` reflect the config the pass depends on.
- Keep passes idempotent and single-purpose; split analysis from transformation.
- `Nodes\AuxiliaryNode` children are traversable but its emitted code is opaque to later passes — the Sandbox docs use this deliberately to mark trusted code. Conversely, don't hide user input inside one.
