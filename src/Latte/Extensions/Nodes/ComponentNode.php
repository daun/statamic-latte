<?php

namespace Daun\StatamicLatte\Latte\Extensions\Nodes;

use Daun\StatamicLatte\Latte\Support\Components;
use Daun\StatamicLatte\Latte\Support\ComponentSlots;
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
 * Replacement node for a `<x-…>` component element dispatched to Blade.
 *
 * Emits a runtime dispatch call to Components::render(), passing the component
 * name, a params array (static strings, dynamic PHP expressions, booleans) and
 * — for paired components — the slots rendered from the component body via
 * output buffering: the loose body as the default slot, plus each named
 * `<x-slot:header>` / `<x-slot name="header">` child as a Blade ComponentSlot.
 */
final class ComponentNode extends StatementNode
{
    public string $name;

    public ArrayNode $params;

    public ?AreaNode $slot = null;

    /** @var array<string, AreaNode> */
    public array $namedSlots = [];

    /** @var array<string, ArrayNode> */
    public array $slotAttributes = [];

    public static function fromElement(ElementNode $element): self
    {
        $node = new self;
        $node->name = Components::unprefix($element->name);
        $node->position = $element->position;
        $node->params = self::parseAttributes($element);

        [$named, $loose] = ComponentSlots::split($element, $node->name);

        foreach ($named as $slotName => $slot) {
            $node->namedSlots[$slotName] = $slot->content ?? new FragmentNode;
            $node->slotAttributes[$slotName] = self::withoutName(self::parseAttributes($slot));
        }

        // The loose body is the default slot. Self-closing / empty components
        // (a NopNode or whitespace only) have no default slot.
        $node->slot = ComponentSlots::hasContent($loose) ? new FragmentNode($loose) : null;

        return $node;
    }

    /** Drop the `name` key of a `<x-slot name="header">` from its attribute bag. */
    protected static function withoutName(ArrayNode $attributes): ArrayNode
    {
        $items = array_filter(
            $attributes->items,
            fn (ArrayItemNode $item): bool => ! ($item->key instanceof IdentifierNode && $item->key->name === 'name'),
        );

        return new ArrayNode(array_values($items));
    }

    /**
     * Build the params array from the element's attributes:
     *   - static  type="error"     -> string literal
     *   - dynamic message={$m}     -> PHP expression
     *   - boolean dismissible      -> true
     *   - spread  ...={$arr} / ...{$arr} -> PHP array unpack
     */
    public static function parseAttributes(ElementNode $element): ArrayNode
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
        if ($this->slot === null && $this->namedSlots === []) {
            return $context->format(
                'echo \Daun\StatamicLatte\Latte\Support\Components::render(%dump, %node) %line;',
                $this->name,
                $this->params,
                $this->position,
            );
        }

        $code = '';
        $default = 'null';

        if ($this->slot !== null) {
            $default = '$ʟ_slot_'.$context->generateId();
            $code .= self::buffer($context, $this->slot, $default);
        }

        $entries = [];
        foreach ($this->namedSlots as $slotName => $content) {
            $var = '$ʟ_slotc_'.$context->generateId();
            $code .= self::buffer($context, $content, $var);
            $attributes = $context->format('%node', $this->slotAttributes[$slotName]);
            $entries[] = var_export($slotName, true).
                ' => new \Illuminate\View\ComponentSlot('.$var.', '.$attributes.')';
        }

        $slots = '['.implode(', ', $entries).']';

        return $code.$context->format(
            'echo \Daun\StatamicLatte\Latte\Support\Components::render(%dump, %node, %raw, %raw) %line;',
            $this->name,
            $this->params,
            $default,
            $slots,
            $this->position,
        );
    }

    /** Wrap slot content in an output buffer captured into $var. */
    protected static function buffer(PrintContext $context, AreaNode $content, string $var): string
    {
        return $context->format(
            <<<'XX'
                ob_start();
                try {
                    %node
                } finally {
                    %raw = ob_get_clean();
                }

                XX,
            $content,
            $var,
        );
    }

    public function &getIterator(): \Generator
    {
        yield $this->params;

        if ($this->slot !== null) {
            yield $this->slot;
        }

        foreach ($this->namedSlots as &$content) {
            yield $content;
        }
        unset($content);

        foreach ($this->slotAttributes as &$attributes) {
            yield $attributes;
        }
        unset($attributes);
    }
}
