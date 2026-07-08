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
use Latte\Compiler\Range;
use Latte\Compiler\Tag;
use Latte\Compiler\Token;
use Latte\Essential\Nodes\BlockNode;
use Latte\Essential\Nodes\EmbedNode;

/**
 * Compile-time desugaring of a `<x-…>` component element into a native Latte
 * `{embed}` + `{block}` subtree.
 *
 *   <x-alert class={'large'}>          {embed components.alert, class: 'large'}
 *     <x-slot name="header">Hi</x-slot>   →   {block header}Hi{/block}
 *     Body                                     {block default}Body{/block}
 *   </x-alert>                          {/embed}
 *
 * Named `<x-slot name="x">` / `<x-slot:x>` children become filled blocks; the
 * remaining loose body becomes the `default` block. Because Latte's embed keeps
 * an isolated block layer, the component template's own `{slot …}` fallbacks
 * render when a slot is omitted, and slot content is evaluated in the caller's
 * scope — matching Blade's ergonomics.
 */
class ComponentEmbed
{
    /**
     * @param  int  $layer  A block-layer id unique to this embed (allocated by
     *                      the compiler pass above any pre-existing embed layer).
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
     * Attributes become the embed's params. With a backing component class the
     * params are replaced by a runtime spread of Components::componentData(),
     * which merges the class's data() over the raw attributes.
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
     * Split the component body into filled blocks: named `<x-slot>` children and
     * the loose body (the `default` block).
     *
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
     * Latte's Block value object requires a Tag. HTML element nodes don't carry
     * one (tags exist only for {…} tags / n:attributes during parsing), so we
     * synthesise a minimal, inert one. Only its name ('block') and int layer are
     * meaningful downstream; TagParser needs at least one positioned token.
     */
    protected static function syntheticTag(ElementNode $element): Tag
    {
        $position = $element->position;
        $range = $position instanceof Range
            ? $position
            : new Range($position->line, $position->column, $position->offset, 0);

        return new Tag('block', [new Token(Token::End, '', $range)], $range);
    }
}
