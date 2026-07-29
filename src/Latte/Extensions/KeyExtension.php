<?php

namespace Daun\StatamicLatte\Latte\Extensions;

use Daun\StatamicLatte\Latte\Extensions\Nodes\KeyNode;
use Latte\Extension;

/**
 * Latte extension adding n:key.
 */
class KeyExtension extends Extension
{
    public function getTags(): array
    {
        return [
            'n:key' => [KeyNode::class, 'create'],
        ];
    }
}
