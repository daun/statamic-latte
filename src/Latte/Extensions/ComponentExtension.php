<?php

namespace Daun\StatamicLatte\Latte\Extensions;

use Daun\StatamicLatte\Latte\Extensions\Nodes\ComponentNode;
use Daun\StatamicLatte\Latte\Support\Components;
use Latte\CompileException;
use Latte\Compiler\Node;
use Latte\Compiler\Nodes\Html\ElementNode;
use Latte\Compiler\Nodes\TemplateNode;
use Latte\Compiler\NodeTraverser;
use Latte\Extension;

/**
 * Latte extension for `<x-…>` components.
 *
 * A compiler pass rewrites every `<x-name>` element (static, dynamic and
 * boolean attributes; paired or self-closing) into a ComponentNode that emits
 * a runtime dispatch call. The dispatch decides Blade vs Latte at render time,
 * so the compiled output is independent of component registrations.
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
        (new NodeTraverser)->traverse($template, function (Node $node) {
            if (! $node instanceof ElementNode || ! self::isComponent($node->name)) {
                return $node;
            }

            if ($node->name === 'x-slot' || str_starts_with($node->name, 'x-slot:')) {
                throw new CompileException(
                    'Named slots (<x-slot:…>) are not supported yet.',
                    $node->position,
                );
            }

            return ComponentNode::fromElement($node);
        });
    }

    protected static function isComponent(string $name): bool
    {
        return str_starts_with(strtolower($name), Components::PREFIX);
    }
}
