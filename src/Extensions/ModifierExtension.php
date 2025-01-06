<?php

namespace Daun\StatamicLatte\Extensions;

use Illuminate\Support\Collection;
use Latte\Engine;
use Latte\Essential\CoreExtension;
use Latte\Extension;
use Statamic\Modifiers\Loader;

/**
 * Latte extension for using Antlers modifiers in Latte templates.
 */
class ModifierExtension extends Extension
{
    protected Engine $latte;

    protected CoreExtension $core;

    protected Loader $loader;

    protected Collection $modifiers;

    public function __construct(Engine $latte)
    {
        $this->latte = $latte;
        $this->core = new CoreExtension;
        $this->loader = app()->make(Loader::class);
        $this->modifiers = app('statamic.modifiers');
    }

    public function getFilters(): array
    {
        return $this->modifiers
            ->except($this->getDefinedFilters())
            ->map(fn ($_, $name) => fn ($value, ...$args) => $this->applyModifier($name, $value, ...$args))
            ->all();
    }

    protected function getDefinedFilters(): array
    {
        // make sure existing filters are not overwritten
        // to only freeze Latte core filters and overwrite user filters, use $this->core->getFilters()
        return array_keys($this->latte->getFilters());
    }

    protected function applyModifier(string $name, $value, ...$args): mixed
    {
        return ($this->loader->load($name))($value, $args, []);
    }
}
