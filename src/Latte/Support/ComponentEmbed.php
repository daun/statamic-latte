<?php

namespace Daun\StatamicLatte\Latte\Support;

use Daun\StatamicLatte\Latte\Extensions\Nodes\ComponentNode;
use Latte\Compiler\Block;
use Latte\Compiler\Nodes\AreaNode;
use Latte\Compiler\Nodes\FragmentNode;
use Latte\Compiler\Nodes\Html\ElementNode;
use Latte\Compiler\Nodes\Php\ArrayItemNode;
use Latte\Compiler\Nodes\Php\Expression\ArrayNode;
use Latte\Compiler\Nodes\Php\Expression\AuxiliaryNode;
use Latte\Compiler\Nodes\Php\ModifierNode;
use Latte\Compiler\Nodes\Php\Scalar\StringNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;
use Latte\Compiler\Token;
use Latte\Essential\Nodes\BlockNode;
use Latte\Essential\Nodes\EmbedNode;

/**
 * Compile-time desugaring of a `<x-…>` element into a native `{embed}` +
 * `{block}` subtree: named slots become filled blocks, the loose body becomes
 * the `default` block. Embed's isolated block layer gives slot fallbacks and
 * caller-scoped slot content for free — matching Blade's ergonomics.
 */
class ComponentEmbed
{
    /**
     * @param  int  $layer  Block-layer id unique to this embed
     */
    public static function fromElement(ElementNode $element, string $name, int $layer): EmbedNode
    {
        $embed = new EmbedNode;
        $embed->name = new StringNode(Components::view($name));
        $embed->mode = 'file';
        $embed->args = self::args($element, $name);
        $embed->blocks = new FragmentNode(self::blocks($element, $name, $layer));
        $embed->layer = $layer;
        $embed->position = $element->position;

        return $embed;
    }

    /**
     * Attributes become the embed's params; with a backing component class,
     * a runtime spread of Components::componentData() instead.
     */
    protected static function args(ElementNode $element, string $name): ArrayNode
    {
        $attributes = ComponentNode::parseAttributes($element);

        if (! Components::latteComponentClass($name)) {
            return $attributes;
        }

        $data = new AuxiliaryNode(
            fn (PrintContext $context, ArrayNode $attrs): string => $context->format(
                '\Daun\StatamicLatte\Latte\Support\Components::componentData(%dump, %node)',
                $name,
                $attrs,
            ),
            [$attributes],
        );

        return new ArrayNode([new ArrayItemNode($data, key: null, unpack: true)]);
    }

    /**
     * @return BlockNode[]
     */
    protected static function blocks(ElementNode $element, string $name, int $layer): array
    {
        $tag = self::syntheticTag($element);
        [$named, $loose] = ComponentSlots::split($element, $name);
        $blocks = [];

        foreach ($named as $slotName => $slot) {
            $blocks[] = self::block($slotName, $slot->content ?? new FragmentNode, $layer, $tag);
        }

        if (ComponentSlots::hasContent($loose)) {
            $blocks[] = self::block('default', new FragmentNode($loose), $layer, $tag);
        }

        return $blocks;
    }

    protected static function block(string $name, AreaNode $content, int $layer, Tag $tag): BlockNode
    {
        $node = new BlockNode;
        $node->block = new Block(new StringNode($name), $layer, $tag);
        $node->modifier = new ModifierNode([]);
        $node->content = $content;
        $node->position = $tag->position;

        return $node;
    }

    /**
     * Latte's Block requires a Tag, which HTML element nodes don't carry, so we
     * synthesise a minimal inert one with a single positioned token.
     */
    protected static function syntheticTag(ElementNode $element): Tag
    {
        $position = $element->position;
        $tokens = [new Token(Token::End, '', $position)];

        $arguments = property_exists($position, 'length')
            ? ['block', $tokens, $position, false]
            : ['block', $tokens, $position, $position];

        return new Tag(...$arguments);
    }
}
