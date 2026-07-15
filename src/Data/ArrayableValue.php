<?php

namespace Daun\StatamicLatte\Data;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Statamic\Contracts\Support\Boolable;
use Statamic\Fields\ArrayableString;
use Statamic\Fields\LabeledValue;
use Stringable;

/**
 * Object-access proxy for Statamic values that behave as both strings and
 * structured arrays, such as links, labeled select values and code fields.
 *
 * @implements ArrayAccess<string, mixed>
 * @implements Arrayable<string, mixed>
 */
final class ArrayableValue implements Arrayable, ArrayAccess, Boolable, JsonSerializable, Stringable
{
    /** @var array<string, mixed> */
    private array $cache = [];

    /** @var array<string, mixed>|null */
    private ?array $data = null;

    public function __construct(private ArrayableString $source) {}

    public function __get(string $key): mixed
    {
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $data = $this->data();

        if (! array_key_exists($key, $data)) {
            return null;
        }

        return $this->cache[$key] = Content::wrap($data[$key]);
    }

    public function __isset(string $key): bool
    {
        return isset($this->data()[$key]);
    }

    public function __set(string $key, mixed $value): void
    {
        throw new \LogicException('Arrayable value wrappers are read-only.');
    }

    public function __unset(string $key): void
    {
        throw new \LogicException('Arrayable value wrappers are read-only.');
    }

    /**
     * @param  array<int, mixed>  $args
     */
    public function __call(string $name, array $args): mixed
    {
        if (method_exists($this->source, $name)) {
            return Content::wrap($this->source->{$name}(...$args));
        }

        throw new \BadMethodCallException(sprintf('Call to undefined method %s::%s()', self::class, $name));
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->__isset((string) $offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->__get((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('Arrayable value wrappers are read-only.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('Arrayable value wrappers are read-only.');
    }

    public function __toString(): string
    {
        return (string) $this->source;
    }

    public function toBool(): bool
    {
        return $this->source->toBool();
    }

    /**
     * Return the scalar shape used before this value entered the proxy.
     */
    public function scalar(): mixed
    {
        return $this->source instanceof LabeledValue
            ? $this->source->value()
            : (string) $this->source;
    }

    /** Return the original Statamic value object. */
    public function source(): ArrayableString
    {
        return $this->source;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->source->toArray();
    }

    public function jsonSerialize(): mixed
    {
        return $this->source->jsonSerialize();
    }

    /**
     * @return array<string, mixed>
     */
    private function data(): array
    {
        return $this->data ??= $this->source->toArray();
    }
}
