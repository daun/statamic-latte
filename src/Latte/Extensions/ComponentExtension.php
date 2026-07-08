<?php

namespace Daun\StatamicLatte\Latte\Extensions;

use Daun\StatamicLatte\Latte\Extensions\Nodes\ComponentNode;
use Daun\StatamicLatte\Latte\Support\ComponentEmbed;
use Daun\StatamicLatte\Latte\Support\Components;
use Latte\CompileException;
use Latte\Compiler\Node;
use Latte\Compiler\Nodes\Html\ElementNode;
use Latte\Compiler\Nodes\TemplateNode;
use Latte\Compiler\NodeTraverser;
use Latte\Essential\Nodes\EmbedNode;
use Latte\Extension;

/**
 * Latte extension for `<x-…>` components.
 *
 * A compiler pass rewrites every `<x-name>` element, choosing its destination
 * at compile time:
 *
 *   • A Latte template `components/<name>.latte` exists  → desugared to a native
 *     `{embed}` + `{block}` subtree (ComponentEmbed). Named `<x-slot>` children
 *     become filled blocks and the loose body becomes the `default` block, so
 *     slots, default-slot fallback and caller-scoped slot content all work.
 *   • Otherwise → a ComponentNode that dispatches to a Blade component at
 *     runtime (class, anonymous or vendor).
 *
 * A Latte template always wins over a Blade component of the same name.
 */
class ComponentExtension extends Extension
{
    public function getPasses(): array
    {
        return [
            'statamic-latte-components' => [self::class, 'componentPass'],
        ];
    }

    public static function componentPass(TemplateNode $template): void
    {
        $layer = self::baseLayer($template);

        (new NodeTraverser)->traverse($template, function (Node $node) use (&$layer) {
            if (! $node instanceof ElementNode || ! self::isComponent($node->name)) {
                return $node;
            }

            if (self::isSlot($node->name)) {
                throw new CompileException(
                    "<{$node->name}> must be a direct child of a component <x-…> element.",
                    $node->position,
                );
            }

            $name = Components::unprefix($node->name);

            if (Components::hasLatteView($name)) {
                return ComponentEmbed::fromElement($node, $name, $layer++);
            }

            return ComponentNode::fromElement($node);
        });
    }

    /**
     * The first block-layer id our synthetic embeds may safely use: one above
     * the highest layer already assigned to a real `{embed}` during parsing, so
     * the two never share a Blocks[] entry.
     */
    protected static function baseLayer(TemplateNode $template): int
    {
        $max = 0;

        (new NodeTraverser)->traverse($template, function (Node $node) use (&$max) {
            if ($node instanceof EmbedNode && is_int($node->layer)) {
                $max = max($max, $node->layer);
            }

            return $node;
        });

        return $max + 1;
    }

    protected static function isComponent(string $name): bool
    {
        return str_starts_with(strtolower($name), Components::PREFIX);
    }

    protected static function isSlot(string $name): bool
    {
        $name = strtolower($name);

        return $name === 'x-slot' || str_starts_with($name, 'x-slot:');
    }
}
