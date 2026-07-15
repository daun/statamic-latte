<?php

namespace Daun\StatamicLatte\Data;

use ArrayAccess;
use Statamic\Facades\Compare;

use function Statamic\View\Blade\value as statamic_value;

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
            // A Deferred proxy resolves like its underlying Value; take the
            // source (cheap) and let the delegation loop peel it.
            if ($value instanceof Deferred) {
                $value = $value->source();
            }
            if ($value instanceof ArrayableValue) {
                $value = $value->scalar();
            }

            // Delegate wrapper peeling to Statamic core so future wrapper types
            // are handled upstream for free. Loop until stable because one
            // unwrap can expose another wrapper (e.g. a Value whose augmented
            // value is an ArrayableString). Statamic's helper does not resolve
            // query builders, so we keep that step ourselves.
            //
            // The object guard bounds the loop: statamic_value() only ever
            // peels wrapper *objects*, so once both the pre- and post-unwrap
            // values are non-objects no further peeling is possible and we
            // stop, even if the identity check alone would keep going.
            do {
                $previous = $value;
                $value = statamic_value($value);
                if (Compare::isQueryBuilder($value)) {
                    $value = $value->get();
                }
            } while ($value !== $previous && (is_object($previous) || is_object($value)));

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
