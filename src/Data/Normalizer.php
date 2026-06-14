<?php

namespace Daun\StatamicLatte\Data;

use Illuminate\Support\Collection as LaravelCollection;
use Statamic\Contracts\Data\Augmentable;
use Statamic\Data\AugmentedCollection;
use Statamic\Facades\Compare;
use Statamic\Fields\ArrayableString;
use Statamic\Fields\LabeledValue;
use Statamic\Fields\Value;
use Statamic\Fields\Values;

/**
 * Normalize Statamic data into predictable PHP shapes for Latte.
 *
 * Rule:
 *   - single augmented thing (Entry/Asset/Term, Values group/grid row) -> Content object
 *   - associative map (keyed collection / keyed array)                 -> Content object
 *   - sequential list (list collection / list array)                  -> plain PHP array
 *   - scalars / unknown objects                                        -> untouched
 *
 * So in templates a keyed thing is always reached with `->` (or `[]`), and you
 * only ever `foreach` over real lists. No guessing array-vs-object.
 */
class Normalizer
{
    /**
     * Normalize a bag of template variables, leaving framework internals alone.
     */
    public static function data(array $data): array
    {
        foreach ($data as $key => $value) {
            $data[$key] = static::normalize($value);
        }

        return $data;
    }

    public static function normalize(mixed $value): mixed
    {
        // Unwrap lazy single values first.
        if ($value instanceof Value) {
            return static::normalize($value->value());
        }
        if ($value instanceof LabeledValue) {
            return $value->value();
        }
        if ($value instanceof ArrayableString) {
            return (string) $value;
        }
        if (Compare::isQueryBuilder($value)) {
            return static::normalize($value->get());
        }

        // Single augmented object -> Content wrapper (object semantics).
        if ($value instanceof Augmentable || $value instanceof Values) {
            return new Content($value);
        }

        // Collections + arrays: shape decides. List -> array, keyed -> object.
        if ($value instanceof AugmentedCollection || $value instanceof LaravelCollection) {
            return static::normalizeArray($value->all());
        }
        if (is_array($value)) {
            return static::normalizeArray($value);
        }

        return $value;
    }

    /**
     * Sequential list -> plain array of normalized children (iterable).
     * Associative map -> Content object (lazy, `->`/`[]` access).
     *
     * @param  array<mixed>  $array
     */
    protected static function normalizeArray(array $array): mixed
    {
        if (array_is_list($array)) {
            return array_map([static::class, 'normalize'], $array);
        }

        return new Content($array);
    }

    /**
     * Inverse of normalize(): peel Content wrappers back to their raw Statamic
     * sources so values can be handed to Statamic modifiers/filters, which
     * predate (and don't understand) the Content wrapper.
     */
    public static function unwrap(mixed $value): mixed
    {
        if ($value instanceof Content) {
            return $value->unwrap();
        }
        if (is_array($value)) {
            return array_map([static::class, 'unwrap'], $value);
        }

        return $value;
    }
}
