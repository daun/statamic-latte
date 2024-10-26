<?php

namespace Daun\StatamicLatte\Extensions;

use Daun\StatamicLatte\Extensions\Nodes\TagNode;
use Daun\StatamicLatte\Support\Tags;
use Illuminate\Support\Collection;
use Latte\Engine;
use Latte\Essential\CoreExtension;
use Latte\Extension;
use Statamic\Tags\Loader;

/**
 * Latte extension for using Antlers tags in Latte templates.
 */
class TagExtension extends Extension
{
    protected Engine $latte;

    protected CoreExtension $core;

    protected Loader $loader;

    protected Collection $tags;

    public function __construct(Engine $latte)
    {
        $this->latte = $latte;
        $this->core = new CoreExtension;
        $this->loader = app()->make(Loader::class);
        $this->tags = app('statamic.tags');
    }

    public function getTags(): array
    {
        return $this->tags
            ->keys()
            ->add('test')
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
