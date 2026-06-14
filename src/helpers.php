<?php

use Daun\StatamicLatte\Data\Resolver;

if (! function_exists('resolve_value')) {
    /**
     * Resolve Statamic values down to their actual final value.
     *
     * @param  mixed  ...$values  One or more values to resolve, in order of preference
     */
    function resolve_value(...$values): mixed
    {
        return Resolver::actual(...$values);
    }
}
