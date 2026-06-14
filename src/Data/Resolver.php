<?php

namespace Daun\StatamicLatte\Data;

use ArrayAccess;
use Statamic\Facades\Compare;
use Statamic\Fields\ArrayableString;
use Statamic\Fields\LabeledValue;
use Statamic\Fields\Value;
use Statamic\Fields\Values;
use Statamic\Modifiers\Modify;
use Statamic\Tags\FluentTag;

/**
 * Resolve Statamic values down to their actual final value.
 *
 * Statamic wraps data in many ways: augmented Value objects, query builders,
 * fluent tags, modifier chains, etc. This unwraps them to the underlying value.
 */
class Resolver
{
    /**
     * Resolve the first non-null value to its actual underlying value.
     *
     * @param  mixed  ...$values  One or more values to resolve, in order of preference
     */
    public static function actual(...$values): mixed
    {
        foreach ($values as $value) {
            if ($value instanceof Values) {
                $value = $value->all();
            }
            if ($value instanceof Value) {
                $value = $value->value();
            }
            if ($value instanceof LabeledValue) {
                $value = $value->value();
            }
            if ($value instanceof ArrayableString) {
                $value = $value->__toString();
            }
            if (Compare::isQueryBuilder($value)) {
                $value = $value->get();
            }
            if ($value instanceof FluentTag) {
                $value = static::actual($value->fetch());
            }
            if ($value instanceof Modify) {
                $value = static::actual($value->fetch());
            }
            if (isset($value)) {
                return $value;
            }
        }

        return $values[0] ?? null;
    }

    /**
     * Resolve a value, then drill into nested keys/properties.
     *
     * Each key may use dot notation (e.g. 'author.name') and the value is
     * re-resolved at every step, so nested wrappers are unwrapped along the way.
     *
     * @param  mixed  $value  The value to resolve and drill into
     * @param  string|int  ...$keys  Keys/properties to drill into
     */
    public static function drill(mixed $value, string|int ...$keys): mixed
    {
        $value = static::actual($value);

        foreach ($keys as $key) {
            foreach (explode('.', (string) $key) as $segment) {
                if ($segment === '') {
                    continue;
                }
                if ($value === null) {
                    return null;
                }
                $value = static::actual(static::get($value, $segment));
            }
        }

        return $value;
    }

    /**
     * Read a single key/property/method off a resolved value.
     */
    protected static function get(mixed $value, string $key): mixed
    {
        if (is_array($value) || $value instanceof ArrayAccess) {
            return $value[$key] ?? null;
        }
        if (is_object($value)) {
            if (isset($value->{$key})) {
                return $value->{$key};
            }
            if (method_exists($value, $key)) {
                return $value->{$key}();
            }
        }

        return null;
    }
}
