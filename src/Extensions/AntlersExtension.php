<?php

namespace Daun\StatamicLatte\Extensions;

use Daun\StatamicLatte\Extensions\Nodes\AntlersNode;
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
