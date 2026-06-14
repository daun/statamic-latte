<?php

namespace Daun\StatamicLatte\Latte\Extensions;

use Daun\StatamicLatte\Latte\Extensions\Nodes\CacheNode;
use Daun\StatamicLatte\Latte\Extensions\Nodes\NocacheNode;
use Latte\Extension;

/**
 * Latte extension for caching views using Statamic's cache.
 */
class CacheExtension extends Extension
{
    public function getTags(): array
    {
        return [
            'cache' => [CacheNode::class, 'create'],
            'nocache' => [NocacheNode::class, 'create'],
        ];
    }
}
