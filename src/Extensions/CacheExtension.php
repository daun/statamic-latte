<?php

namespace Daun\StatamicLatte\Extensions;

use Daun\StatamicLatte\Extensions\Nodes\CacheNode;
use Daun\StatamicLatte\Extensions\Nodes\NocacheNode;
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
