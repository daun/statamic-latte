<?php

namespace Daun\StatamicLatte\Latte\Extensions;

use Daun\StatamicLatte\Latte\Extensions\Nodes\AntlersNode;
use Latte\Extension;

/**
 * Latte extension for rendering Antlers views inside Latte templates.
 */
class AntlersExtension extends Extension
{
    public function getTags(): array
    {
        return ['antlers' => [AntlersNode::class, 'create']];
    }
}
