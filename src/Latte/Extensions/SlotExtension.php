<?php

namespace Daun\StatamicLatte\Latte\Extensions;

use Daun\StatamicLatte\Latte\Extensions\Nodes\SlotNode;
use Latte\Engine;
use Latte\Extension;

/**
 * Adds {slot} as an alias for the core {block} tag. The resulting node is a
 * real BlockNode, so it satisfies {embed}'s content check on its body, and
 * filled slots see the caller's variables just like a filled {block}.
 */
class SlotExtension extends Extension
{
    public function getTags(): array
    {
        return [
            'slot' => [SlotNode::class, 'create'],
        ];
    }

    /**
     * Bump when the compiled shape of slots changes, so templates compiled by
     * an older version are recompiled instead of served stale.
     */
    public function getCacheKey(Engine $engine): mixed
    {
        return 'caller-scope';
    }
}
