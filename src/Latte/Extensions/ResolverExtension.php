<?php

namespace Daun\StatamicLatte\Latte\Extensions;

use Daun\StatamicLatte\Data\Resolver;
use Latte\Extension;

/**
 * Latte extension exposing the value resolver.
 *
 * Adds a resolve() function and a |resolve filter that unwrap Statamic
 * values (query builders, augmented values, fluent tags, …) to their
 * actual underlying value.
 */
class ResolverExtension extends Extension
{
    public function getFunctions(): array
    {
        return [
            'resolve' => [Resolver::class, 'actual'],
            'r' => [Resolver::class, 'actual'],
        ];
    }

    public function getFilters(): array
    {
        return [
            'resolve' => [Resolver::class, 'drill'],
        ];
    }
}
