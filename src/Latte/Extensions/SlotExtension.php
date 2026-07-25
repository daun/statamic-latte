<?php

namespace Daun\StatamicLatte\Latte\Extensions;

use Latte\Essential\Nodes\BlockNode;
use Latte\Extension;

/**
 * Adds {slot} as a pure alias for the core {block} tag. The resulting node is
 * a real BlockNode, so it satisfies {embed}'s content check on its body.
 */
class SlotExtension extends Extension
{
    public function getTags(): array
    {
        return [
            'slot' => [BlockNode::class, 'create'],
        ];
    }
}
