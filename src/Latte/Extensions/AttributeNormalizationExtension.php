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
 * Teaches Latte's native n:attr to accept Content objects: its runtime does an
 * is_array() check that would silently drop them, so this pass wraps every
 * n:attr argument in Content::unwrap() (a no-op for scalars).
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
