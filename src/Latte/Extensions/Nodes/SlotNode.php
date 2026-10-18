<?php

namespace Daun\StatamicLatte\Latte\Extensions\Nodes;

use Daun\StatamicLatte\Latte\Support\SyntheticBlockTag;
use Latte\Compiler\Block;
use Latte\Compiler\Nodes\AreaNode;
use Latte\Compiler\Nodes\Php\Scalar\IntegerNode;
use Latte\Compiler\Nodes\Php\Scalar\StringNode;
use Latte\Compiler\Tag;
use Latte\Compiler\TemplateParser;
use Latte\Essential\Nodes\BlockNode;

/**
 * {slot name} ... {/slot}
 *
 * Parsed and rendered exactly like the core {block}: the node IS a BlockNode,
 * so it passes {embed}'s content check and `n:slot` / `n:inner-slot` come free.
 */
class SlotNode extends BlockNode
{
    /** @return \Generator<int, ?list<string>, array{AreaNode, ?Tag}, static> */
    public static function create(Tag $tag, TemplateParser $parser): \Generator
    {
        $node = yield from parent::create($tag, $parser);

        if ($node->block && static::isEmbedded($node->block)) {
            $node->block = static::retag($node->block, $tag, $parser);
        }

        return $node;
    }

    /**
     * Static, named slot filled inside an {embed} — the only case where Latte
     * gives a block the caller's variables, and only if its tag is a {block}.
     */
    protected static function isEmbedded(Block $block): bool
    {
        return ! $block->isDynamic() && is_int($block->layer) && $block->layer > 0;
    }

    /**
     * Swap in an identical block carrying a synthetic {block} tag. Parsing is
     * done by now, so diagnostics and end-tag matching still said {slot}.
     */
    protected static function retag(Block $block, Tag $tag, TemplateParser $parser): Block
    {
        $retagged = new Block(
            $block->name,
            $block->layer,
            SyntheticBlockTag::from($tag),
        );
        $retagged->parameters = $block->parameters;

        $name = $block->name instanceof StringNode || $block->name instanceof IntegerNode
            ? $block->name->value
            : null;

        if ($name !== null && ($parser->blocks[$block->layer][$name] ?? null) === $block) {
            $parser->blocks[$block->layer][$name] = $retagged;
        }

        return $retagged;
    }
}
