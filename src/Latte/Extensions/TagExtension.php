<?php

namespace Daun\StatamicLatte\Latte\Extensions;

use Daun\StatamicLatte\Latte\Extensions\Nodes\TagNode;
use Daun\StatamicLatte\Latte\Support\Tags;
use Illuminate\Support\Collection;
use Latte\Engine;
use Latte\Extension;

/**
 * Latte extension for using Statamic tags in Latte templates.
 *
 * Exposes every registered Statamic tag as a prefixed Latte tag
 * (e.g. {s:collection}) and as the statamic()/s() functions.
 */
class TagExtension extends Extension
{
    protected Collection $tags;

    public function __construct(Engine $latte)
    {
        $this->tags = app('statamic.tags');
    }

    public function getTags(): array
    {
        return $this->tags
            ->keys()
            ->map(fn ($tag) => Tags::prefix($tag))
            ->mapWithKeys(fn ($tag) => [$tag => [TagNode::class, 'create']])
            ->all();
    }

    public function getFunctions(): array
    {
        return [
            'statamic' => [Tags::class, 'fetch'],
            's' => [Tags::class, 'fetch'],
        ];
    }
}
