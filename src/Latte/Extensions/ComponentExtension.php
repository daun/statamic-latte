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
 * Compiler pass rewriting every `<x-name>` element: to an {embed} subtree when
 * a Latte template `components/<name>.latte` exists (ComponentEmbed), else to
 * a runtime Blade dispatch (ComponentNode). Latte always wins over Blade.
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
     * First block-layer id our synthetic embeds may use: one above the highest
     * layer of any real `{embed}`, so the two never share a Blocks[] entry.
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
