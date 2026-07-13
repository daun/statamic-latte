<?php

namespace Daun\StatamicLatte\Latte\Extensions;

use Daun\StatamicLatte\Data\Content;
use Illuminate\Support\Collection;
use Latte\Engine;
use Latte\Essential\CoreExtension;
use Latte\Extension;
use Statamic\Modifiers\CoreModifiers;
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
        [$user, $core] = $this->modifiers->partition(
            fn ($reference) => $this->isUserModifier($reference)
        );

        return $user
            ->merge($core->except($this->getDefinedFilters()))
            ->map(fn ($_, $name) => fn ($value, ...$args) => $this->applyModifier($name, $value, ...$args))
            ->all();
    }

    protected function isUserModifier($reference): bool
    {
        return is_string($reference) && ! str_contains($reference, CoreModifiers::class.'@');
    }

    protected function getDefinedFilters(): array
    {
        // Make sure existing filters are not overwritten
        // (to only freeze Latte core filters and overwrite user filters, use $this->core->getFilters())
        return array_keys($this->latte->getFilters());
    }

    protected function applyModifier(string $name, $value, ...$args): mixed
    {
        // Peel Content wrappers back to raw Statamic data before handing off
        // to modifiers, which expect plain values/arrays/augmentables.
        $value = Content::unwrap($value);
        $args = array_map([Content::class, 'unwrap'], $args);

        return ($this->loader->load($name))($value, $args, []);
    }
}
