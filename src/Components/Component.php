<?php

namespace Daun\StatamicLatte\Components;

/**
 * Optional backing class for a `<x-…>` Latte component template. Constructor
 * parameters are filled from the tag's attributes (via the container), and
 * data() is spread into the template's variables.
 */
abstract class Component
{
    /**
     * Variables exposed to the component's template; defaults to the public
     * properties, override to compute derived values.
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return get_object_vars($this);
    }
}
