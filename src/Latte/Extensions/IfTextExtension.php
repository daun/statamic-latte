<?php

namespace Daun\StatamicLatte\Latte\Extensions;

use Daun\StatamicLatte\Latte\Extensions\Nodes\IfTextNode;
use Latte\Extension;

/**
 * Latte extension adding {iftext} and n:iftext.
 */
class IfTextExtension extends Extension
{
    public function getTags(): array
    {
        return [
            'iftext' => [IfTextNode::class, 'create'],
        ];
    }
}
