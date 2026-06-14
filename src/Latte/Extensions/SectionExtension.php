<?php

namespace Daun\StatamicLatte\Latte\Extensions;

use Daun\StatamicLatte\Latte\Extensions\Nodes\SectionNode;
use Daun\StatamicLatte\Latte\Extensions\Nodes\YieldNode;
use Latte\Extension;

/**
 * Latte extension exposing Statamic's section/yield content bus.
 *
 * {section 'name'} ... {/section}  — define content under a name
 * {yield 'name'}                   — output it, wherever it was defined
 *
 * Backed by the Statamic Cascade and Laravel's view factory, so sections and
 * yields interoperate across Latte, Antlers and Blade templates.
 */
class SectionExtension extends Extension
{
    public function getTags(): array
    {
        return [
            'section' => [SectionNode::class, 'create'],
            'yield' => [YieldNode::class, 'create'],
        ];
    }
}
