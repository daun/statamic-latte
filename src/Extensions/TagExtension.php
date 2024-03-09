<?php

namespace Daun\StatamicLatte\Extensions;

use Daun\StatamicLatte\Extensions\Nodes\StatamicNode;
use Illuminate\Support\Collection;
use Latte\Compiler\Nodes\TextNode;
use Latte\Compiler\Tag;
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
        $this->core = new CoreExtension();
        $this->loader = app()->make(Loader::class);
        $this->tags = app('statamic.tags');
    }

    public function getTags(): array
    {
        // return ['statamic' => [StatamicNode::class, 'create']];

        return $this->tags
            ->except($this->getCoreTags())
            ->map(fn ($_, $name) => fn (Tag $tag) => new TextNode("{statamic tag : {$name}}", $tag->position))
            ->keyBy(fn ($_, $name) => "statamic:{$name}")
            ->all();
    }

    protected function getCoreTags(): array
    {
        return array_keys($this->core->getTags());
    }
}
