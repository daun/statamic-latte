<?php

namespace Daun\StatamicLatte\Data;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Statamic\Fields\Value;
use Traversable;

/**
 * Lazy proxy for a non-empty relationship field at the render boundary.
 *
 * The cascade hands every blueprint field to the engine as a deferred
 * {@see Value}. For relationship fieldtypes (entries, terms, assets, users)
 * calling ->value() runs the field's query builder and augments the results —
 * expensive, and paid for every relationship field whether the template uses
 * it or not. Deferred postpones that work until the template first touches the
 * variable (property access, iteration, count, echo, unwrap).
 *
 * Only created by {@see Content::wrapAll()} and only for relationship Values
 * whose raw stored value is non-empty. Emptiness is decided from the raw value
 * (IDs) without augmenting, so an empty relationship is never deferred — its
 * eager, correctly-falsy [] / null is preserved. Because we only wrap
 * non-empty values, the object's always-truthy nature is correct: {if $related}
 * behaves exactly as before.
 *
 * @implements ArrayAccess<string, mixed>
 * @implements IteratorAggregate<string, mixed>
 */
final class Deferred implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    private mixed $resolved = null;

    private bool $isResolved = false;

    public function __construct(private Value $value) {}

    /**
     * Materialize the underlying Value into its template shape (a Content for a
     * single related item, or a plain array of Content for a list). Cached.
     */
    public function materialize(): mixed
    {
        if (! $this->isResolved) {
            $this->resolved = Content::wrap($this->value);
            $this->isResolved = true;
        }

        return $this->resolved;
    }

    /** The underlying deferred Value (for unwrap()/resolve() boundaries). */
    public function source(): Value
    {
        return $this->value;
    }

    public function __get(string $key): mixed
    {
        $resolved = $this->materialize();

        if (is_array($resolved)) {
            return $resolved[$key] ?? null;
        }
        if (is_object($resolved)) {
            return $resolved->{$key} ?? null;
        }

        return null;
    }

    public function __isset(string $key): bool
    {
        return $this->offsetExists($key);
    }

    public function offsetExists(mixed $offset): bool
    {
        // Content implements ArrayAccess, so isset[] covers both the array
        // (list) and Content (single item) materialized shapes.
        $resolved = $this->materialize();

        return is_array($resolved) || $resolved instanceof ArrayAccess
            ? isset($resolved[$offset])
            : false;
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->__get((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('Deferred wrappers are read-only.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('Deferred wrappers are read-only.');
    }

    public function getIterator(): Traversable
    {
        $resolved = $this->materialize();

        if ($resolved instanceof Traversable) {
            return $resolved;
        }

        return new ArrayIterator(is_array($resolved) ? $resolved : []);
    }

    public function count(): int
    {
        // Always count the materialized value, never the raw ID list: a raw ID
        // may point at an unpublished or deleted entry that augmentation drops,
        // so a raw-ID count could report more items than a {foreach} yields.
        // Correctness beats saving one augmentation here — counting is a touch.
        $resolved = $this->materialize();

        if (is_array($resolved)) {
            return count($resolved);
        }
        if ($resolved instanceof Countable) {
            return count($resolved);
        }

        return $resolved === null ? 0 : 1;
    }

    public function jsonSerialize(): mixed
    {
        // Peel Content wrappers back to their raw Statamic sources so json_encode
        // emits real entry data, not the empty objects a bare Content produces.
        return Content::unwrap($this->materialize());
    }

    /**
     * Echoing a relationship directly is not a supported template pattern (it
     * was never scalar). Materialize and print only if the result is a scalar
     * string; otherwise print nothing rather than fataling on an object cast.
     */
    public function __toString(): string
    {
        $resolved = $this->materialize();

        if (is_scalar($resolved) || $resolved instanceof \Stringable) {
            return (string) $resolved;
        }

        return '';
    }
}
