<?php

namespace Daun\StatamicLatte\Data;

use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;
use Statamic\Contracts\Data\Augmentable;
use Statamic\Contracts\Data\Augmented;
use Statamic\Fields\Values;
use Traversable;

/**
 * Lazy object wrapper around a single keyed source: an augmented Statamic
 * value-set (Entry/Asset/Term, Values group/grid row) or a plain associative
 * array/map.
 *
 * Property access resolves exactly one key on demand:
 *   {$entry->title}          -> augmentedValue('title')->value()
 *   {$entry->author->name}   -> nested Content, augmented lazily
 *
 * No magic method dispatch (`__call`) by design — properties only.
 * Supports both `->key` and `['key']` so a template never guesses wrong.
 *
 * Iterable too: `{foreach $content as $key => $value}` walks its keys (each
 * resolved lazily). Iterating an Augmentable forces full augmentation — that's
 * an explicit, rare act, so the cost is opt-in.
 *
 * @implements ArrayAccess<string, mixed>
 * @implements IteratorAggregate<string, mixed>
 */
class Content implements ArrayAccess, IteratorAggregate
{
    /** @var array<string, mixed> Normalized per-key cache. */
    protected array $cache = [];

    protected ?Augmented $augmented = null;

    /**
     * @param  Augmentable|Values|array<string, mixed>  $source
     */
    public function __construct(
        protected Augmentable|Values|array $source,
    ) {}

    public function __get(string $key): mixed
    {
        return $this->resolve($key);
    }

    public function __isset(string $key): bool
    {
        return $this->has($key);
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->has((string) $offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->resolve((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('Content wrappers are read-only.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('Content wrappers are read-only.');
    }

    public function getIterator(): Traversable
    {
        $resolved = [];
        foreach ($this->keys() as $key) {
            $resolved[$key] = $this->resolve($key);
        }

        return new ArrayIterator($resolved);
    }

    protected function resolve(string $key): mixed
    {
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        return $this->cache[$key] = Normalizer::normalize($this->rawValue($key));
    }

    protected function rawValue(string $key): mixed
    {
        if (is_array($this->source)) {
            return $this->source[$key] ?? null;
        }
        if ($this->source instanceof Values) {
            // offsetGet triggers augmentation, returns the already-augmented value.
            return $this->source[$key] ?? null;
        }

        // Augmentable: augmentedValue() returns a lazy Value, normalized later.
        return $this->source->augmentedValue($key);
    }

    protected function has(string $key): bool
    {
        if (is_array($this->source)) {
            return array_key_exists($key, $this->source);
        }
        if ($this->source instanceof Values) {
            return isset($this->source[$key]);
        }

        return in_array($key, $this->keys(), true);
    }

    /**
     * @return array<int, string>
     */
    protected function keys(): array
    {
        if (is_array($this->source)) {
            return array_keys($this->source);
        }
        if ($this->source instanceof Values) {
            // Proxies a Collection; keys() returns field handles, no augmentation.
            return $this->source->keys()->all();
        }

        $this->augmented ??= $this->source->augmented();

        return $this->augmented->keys();
    }

    /** Escape hatch: get the underlying source. */
    public function unwrap(): Augmentable|Values|array
    {
        return $this->source;
    }
}
