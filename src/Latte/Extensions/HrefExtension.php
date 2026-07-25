<?php

namespace Daun\StatamicLatte\Latte\Extensions;

use Daun\StatamicLatte\Latte\Extensions\Nodes\HrefNode;
use Latte\Extension;

/**
 * Latte extension adding the n:href attribute.
 */
class HrefExtension extends Extension
{
    public function getTags(): array
    {
        return [
            'n:href' => [HrefNode::class, 'create'],
        ];
    }
}
