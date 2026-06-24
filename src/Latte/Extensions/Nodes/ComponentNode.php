<?php

namespace Daun\StatamicLatte\Latte\Extensions\Nodes;

use Daun\StatamicLatte\Latte\Support\Components;
use Latte\CompileException;
use Latte\Compiler\Nodes\AreaNode;
use Latte\Compiler\Nodes\FragmentNode;
use Latte\Compiler\Nodes\Html\AttributeNode;
use Latte\Compiler\Nodes\Html\ElementNode;
use Latte\Compiler\Nodes\Html\ExpressionAttributeNode;
use Latte\Compiler\Nodes\Php\ArrayItemNode;
use Latte\Compiler\Nodes\Php\Expression\ArrayNode;
use Latte\Compiler\Nodes\Php\ExpressionNode;
use Latte\Compiler\Nodes\Php\IdentifierNode;
use Latte\Compiler\Nodes\Php\Scalar\BooleanNode;
use Latte\Compiler\Nodes\Php\Scalar\StringNode;
use Latte\Compiler\Nodes\PrintNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\Nodes\TextNode;
use Latte\Compiler\PrintContext;

/**
 * Replacement node for a `<x-…>` component element.
 *
 * Emits a single neutral runtime dispatch call to Components::render(), passing
 * the component name, a params array (static strings, dynamic PHP expressions,
 * booleans) and — for paired components — a default-slot string rendered from
 * the component body via output buffering.
 */
final class ComponentNode extends StatementNode
{
    public string $name;

    public ArrayNode $params;

    public ?AreaNode $slot = null;

    public static function fromElement(ElementNode $element): self
    {
        $node = new self;
        $node->name = Components::unprefix($element->name);
        $node->position = $element->position;
        $node->params = self::parseAttributes($element);

        // Paired components carry their body as a FragmentNode (the default
        // slot). Self-closing / empty components have a NopNode or null here.
        if ($element->content instanceof FragmentNode) {
            $node->slot = $element->content;
        }

        return $node;
    }

    /**
     * Build the params array from the element's attributes:
     *   - static  type="error"     -> string literal
     *   - dynamic message={$m}     -> PHP expression
     *   - boolean dismissible      -> true
     *   - spread  ...={$arr} / ...{$arr} -> PHP array unpack
     */
    protected static function parseAttributes(ElementNode $element): ArrayNode
    {
        $items = [];
        $spreadPending = false;

        foreach ($element->attributes->children as $child) {
            if ($child instanceof ExpressionAttributeNode) {
                // Spread:  ...={$array}  -> PHP array unpack into the params.
                // Later attributes override spread entries (source order wins).
                if ($child->name === '...') {
                    $items[] = new ArrayItemNode($child->value, key: null, unpack: true);

                    continue;
                }

                $items[] = new ArrayItemNode($child->value, new IdentifierNode($child->name));

                continue;
            }

            if (! $child instanceof AttributeNode) {
                continue;
            }

            // Spread:  ...{$array}  parses as a boolean `...` attribute followed
            // by an attribute whose *name* is the {$array} print. Stitch the
            // two halves back into a single array unpack.
            if ($spreadPending) {
                $items[] = new ArrayItemNode(self::spreadExpression($child, $element), key: null, unpack: true);
                $spreadPending = false;

                continue;
            }

            if ($child->name instanceof TextNode && $child->name->content === '...' && $child->value === null) {
                $spreadPending = true;

                continue;
            }

            if (! $child->name instanceof TextNode) {
                throw new CompileException(
                    "Dynamic attribute names are not supported on <{$element->name}>.",
                    $element->position,
                );
            }

            $name = $child->name->content;

            if (str_starts_with($name, '...')) {
                throw new CompileException(
                    "To spread attributes onto <{$element->name}>, use ...={\$array} or ...{\$array} ".
                    '(with braces around the expression).',
                    $element->position,
                );
            }

            if ($child->value === null) {
                $items[] = new ArrayItemNode(new BooleanNode(true), new IdentifierNode($name));

                continue;
            }

            if ($child->value instanceof TextNode) {
                $items[] = new ArrayItemNode(new StringNode($child->value->content), new IdentifierNode($name));

                continue;
            }

            throw new CompileException(
                "Interpolated attribute values are not supported on <{$element->name}>; ".
                "use {$name}={\$expr} instead.",
                $element->position,
            );
        }

        if ($spreadPending) {
            throw new CompileException(
                "A spread `...` on <{$element->name}> must be followed by {\$array}.",
                $element->position,
            );
        }

        return new ArrayNode($items);
    }

    /**
     * Pull the array expression out of the `{$array}` half of a `...{$array}`
     * spread, which Latte parses as an attribute whose name is a PrintNode.
     */
    protected static function spreadExpression(AttributeNode $child, ElementNode $element): ExpressionNode
    {
        if (! $child->name instanceof PrintNode || $child->value !== null) {
            throw new CompileException(
                "A spread `...` on <{$element->name}> must be followed by {\$array}.",
                $element->position,
            );
        }

        if ($child->name->modifier->filters !== []) {
            throw new CompileException(
                "Filters are not supported on a spread expression on <{$element->name}>.",
                $element->position,
            );
        }

        return $child->name->expression;
    }

    public function print(PrintContext $context): string
    {
        if ($this->slot === null) {
            return $context->format(
                'echo \Daun\StatamicLatte\Latte\Support\Components::render(%dump, %node) %line;',
                $this->name,
                $this->params,
                $this->position,
            );
        }

        $slot = '$ʟ_slot_'.$context->generateId();

        return $context->format(
            <<<'XX'
                ob_start();
                try {
                    %node
                } finally {
                    %raw = ob_get_clean();
                }
                echo \Daun\StatamicLatte\Latte\Support\Components::render(%dump, %node, %raw) %line;
                XX,
            $this->slot,
            $slot,
            $this->name,
            $this->params,
            $slot,
            $this->position,
        );
    }

    public function &getIterator(): \Generator
    {
        yield $this->params;
        if ($this->slot !== null) {
            yield $this->slot;
        }
    }
}
