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
 * Lazy proxy postponing a relationship field's query + augmentation until the
 * template first touches it. Only created for non-empty relationships (decided
 * from the raw IDs), so the object's always-truthy nature is correct and empty
 * relationships keep their eager, falsy [] / null.
 *
 * @implements ArrayAccess<string, mixed>
 * @implements IteratorAggregate<string, mixed>
 */
final class Deferred implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    private mixed $resolved = null;

    private bool $isResolved = false;

    public function __construct(private Value $value) {}

    /** Materialize into template shape (Content for one item, array for a list). Cached. */
    public function materialize(): mixed
    {
        if (! $this->isResolved) {
            $this->resolved = Content::wrap($this->value);
            $this->isResolved = true;
        }

        return $this->resolved;
    }

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

    /**
     * Forward method calls to the materialized Content (single-item shape only).
     *
     * @param  array<int, mixed>  $args
     */
    public function __call(string $name, array $args): mixed
    {
        $resolved = $this->materialize();

        if ($resolved instanceof Content) {
            return $resolved->{$name}(...$args);
        }

        throw new \BadMethodCallException(sprintf('Call to undefined method %s::%s()', self::class, $name));
    }

    public function offsetExists(mixed $offset): bool
    {
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
        // Count materialized, never raw IDs: augmentation may drop unpublished
        // or deleted entries, so a raw count could exceed what {foreach} yields.
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
        // Unwrap so json_encode emits real entry data, not empty Content objects.
        return Content::unwrap($this->materialize());
    }

    /** Print scalar results, print nothing otherwise instead of fataling. */
    public function __toString(): string
    {
        $resolved = $this->materialize();

        if (is_scalar($resolved) || $resolved instanceof \Stringable) {
            return (string) $resolved;
        }

        return '';
    }
}
