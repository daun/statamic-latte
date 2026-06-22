<?php

namespace Daun\StatamicLatte\Latte\Extensions;

use Latte\Essential\Nodes\BlockNode;
use Latte\Extension;

/**
 * Latte extension that adds {slot} as an alias for the core {block} tag.
 *
 * {slot} is a pure synonym for {block} and works in every context a block
 * does: defining a named, fillable region in a partial and filling it from
 * inside an {embed}. Parsing and rendering defer entirely to {block}, and
 * because the resulting node is a BlockNode it satisfies the content check
 * that {embed} performs on its body.
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
