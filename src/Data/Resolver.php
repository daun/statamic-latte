<?php

namespace Daun\StatamicLatte\Data;

use ArrayAccess;
use Statamic\Facades\Compare;

use function Statamic\View\Blade\value as statamic_value;

/**
 * Resolves Statamic's many wrappers (augmented Values, query builders, fluent
 * tags, ...) down to their actual underlying value.
 */
class Resolver
{
    /** Resolve the first non-null of the given values, in order of preference. */
    public static function actual(...$values): mixed
    {
        foreach ($values as $value) {
            if ($value instanceof Deferred) {
                $value = $value->source();
            }
            if ($value instanceof ArrayableValue || $value instanceof HtmlValue) {
                $value = $value->scalar();
            }

            // Peel wrappers via Statamic core, looping until stable since one
            // unwrap can expose another. The object guard bounds the loop:
            // statamic_value() only peels objects, so two non-object rounds
            // mean we're done even if the identity check would keep going.
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
     * Resolve a value, then drill into nested keys/properties (dot notation
     * allowed), re-resolving at every step.
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

    /** Read a single key/property/method off a resolved value. */
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
