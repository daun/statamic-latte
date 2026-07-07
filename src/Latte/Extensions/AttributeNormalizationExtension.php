<?php

namespace Daun\StatamicLatte\Latte\Extensions;

use Latte\Compiler\Node;
use Latte\Compiler\Nodes\Php\Expression\AuxiliaryNode;
use Latte\Compiler\Nodes\Php\ExpressionNode;
use Latte\Compiler\Nodes\TemplateNode;
use Latte\Compiler\NodeTraverser;
use Latte\Compiler\PrintContext;
use Latte\Essential\Nodes\NAttrNode;
use Latte\Extension;

/**
 * Teaches Latte's native {n:attr} to accept Statamic Content objects.
 *
 * Content::wrap maps keyed arrays to a Content object so templates reach them
 * with `->`. Latte's n:attr runtime does an is_array() check, so a Content is
 * silently dropped — `<div n:attr="$attrs">` would render no attributes. This
 * pass wraps every n:attr argument in Content::unwrap(), peeling Content
 * back to a plain array at that boundary. unwrap() is a no-op for scalars and
 * strings, so the keyed forms (`n:attr="href: …, class: …"`) are unaffected.
 */
class AttributeNormalizationExtension extends Extension
{
    public function getPasses(): array
    {
        return [
            'statamic-latte-attribute-normalization' => [self::class, 'unwrapPass'],
        ];
    }

    public static function unwrapPass(TemplateNode $template): void
    {
        (new NodeTraverser)->traverse($template, function (Node $node) {
            if ($node instanceof NAttrNode) {
                foreach ($node->args->items as $item) {
                    $item->value = self::unwrap($item->value);
                }
            }

            return $node;
        });
    }

    protected static function unwrap(ExpressionNode $value): AuxiliaryNode
    {
        return new AuxiliaryNode(
            fn (PrintContext $context, ExpressionNode $inner): string => '\Daun\StatamicLatte\Data\Content::unwrap('.$inner->print($context).')',
            [$value],
        );
    }
}
