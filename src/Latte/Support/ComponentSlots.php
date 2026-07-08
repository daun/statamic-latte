<?php

namespace Daun\StatamicLatte\Latte\Support;

use Latte\CompileException;
use Latte\Compiler\Nodes\AreaNode;
use Latte\Compiler\Nodes\FragmentNode;
use Latte\Compiler\Nodes\Html\AttributeNode;
use Latte\Compiler\Nodes\Html\ElementNode;
use Latte\Compiler\Nodes\NopNode;
use Latte\Compiler\Nodes\TextNode;

/**
 * Splits a `<x-…>` component body into named slots and loose (default) content.
 * Shared by both dispatch paths: ComponentEmbed maps slots to `{embed}` blocks,
 * ComponentNode buffers them into Blade `ComponentSlot`s.
 *
 * Named slots use either `<x-slot:header>` or `<x-slot name="header">`; the
 * remaining body is the default slot.
 */
class ComponentSlots
{
    /**
     * @return array{0: array<string, ElementNode>, 1: AreaNode[]} named slot
     *                                                             elements keyed by name, plus the loose (default-slot) children
     */
    public static function split(ElementNode $element, string $component): array
    {
        $named = [];
        $loose = [];

        foreach (self::children($element) as $child) {
            if ($child instanceof ElementNode && self::isSlotElement($child)) {
                $named[self::slotName($child, $component)] = $child;

                continue;
            }

            $loose[] = $child;
        }

        return [$named, $loose];
    }

    /** @return AreaNode[] */
    public static function children(ElementNode $element): array
    {
        if ($element->content instanceof FragmentNode) {
            return $element->content->children;
        }

        return $element->content !== null ? [$element->content] : [];
    }

    public static function isSlotElement(ElementNode $element): bool
    {
        $name = strtolower($element->name);

        return $name === 'x-slot' || str_starts_with($name, 'x-slot:');
    }

    /** Resolve the slot name from `<x-slot:header>` or `<x-slot name="header">`. */
    public static function slotName(ElementNode $slot, string $component): string
    {
        if (str_contains($slot->name, ':')) {
            return substr($slot->name, strpos($slot->name, ':') + 1);
        }

        $name = self::staticAttribute($slot, 'name');

        if ($name === null || $name === '') {
            throw new CompileException(
                "A <x-slot> in component <x-{$component}> needs a name: ".
                'use <x-slot:header> or <x-slot name="header">.',
                $slot->position,
            );
        }

        return $name;
    }

    public static function staticAttribute(ElementNode $element, string $key): ?string
    {
        foreach ($element->attributes->children as $attribute) {
            if ($attribute instanceof AttributeNode
                && $attribute->name instanceof TextNode
                && $attribute->name->content === $key
                && $attribute->value instanceof TextNode
            ) {
                return $attribute->value->content;
            }
        }

        return null;
    }

    /** Whether the loose body has real content worth a default slot. */
    public static function hasContent(array $children): bool
    {
        foreach ($children as $child) {
            // A self-closing / empty element carries a NopNode; whitespace-only
            // text is insignificant. Neither suppresses a default-slot fallback.
            if ($child instanceof NopNode) {
                continue;
            }

            if ($child instanceof TextNode && trim($child->content) === '') {
                continue;
            }

            return true;
        }

        return false;
    }
}
