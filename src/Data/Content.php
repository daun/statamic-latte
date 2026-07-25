<?php

namespace Daun\StatamicLatte\Data;

use ArrayAccess;
use ArrayIterator;
use Illuminate\Support\Collection as LaravelCollection;
use IteratorAggregate;
use Statamic\Contracts\Data\Augmentable;
use Statamic\Contracts\Data\Augmented;
use Statamic\Data\AugmentedCollection;
use Statamic\Facades\Compare;
use Statamic\Fields\ArrayableString;
use Statamic\Fields\Value;
use Statamic\Fields\Values;
use Traversable;

/**
 * Lazy read-only wrapper around a keyed source (augmented Statamic value-set or
 * assoc array), resolving one key per `->key`/`['key']` access. Method calls
 * pass through to the source, with destructive methods blocked.
 *
 * @implements ArrayAccess<string, mixed>
 * @implements IteratorAggregate<string, mixed>
 */
class Content implements ArrayAccess, IteratorAggregate
{
    /**
     * @var array<int, string>
     */
    protected const GUARDED_METHODS = [
        'delete', 'deletequietly',
        'save', 'savequietly',
        'set', 'setsupplement', 'remove', 'removesupplement', 'merge',
        'move', 'rename', 'replace', 'reupload',
    ];

    /** @var array<string, mixed> */
    protected array $cache = [];

    protected ?Augmented $augmented = null;

    /**
     * @param  Augmentable|Values|array<string, mixed>  $source
     * @param  list<string>  $htmlKeys  Keys holding rendered HTML (see wrapSets())
     */
    public function __construct(
        protected Augmentable|Values|array $source,
        protected array $htmlKeys = [],
    ) {}

    public function __get(string $key): mixed
    {
        return $this->resolve($key);
    }

    public function __isset(string $key): bool
    {
        return $this->has($key);
    }

    /**
     * @param  array<int, mixed>  $args
     */
    public function __call(string $name, array $args): mixed
    {
        if (in_array(strtolower($name), static::GUARDED_METHODS, true)) {
            throw new \LogicException("Method {$name}() is not allowed on wrapped content: wrappers are read-only.");
        }

        if (! is_array($this->source) && method_exists($this->source, $name)) {
            return static::wrap($this->source->{$name}(...$args));
        }

        throw new \BadMethodCallException(sprintf('Call to undefined method %s::%s()', static::class, $name));
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

        $value = static::wrap($this->rawValue($key));

        if (in_array($key, $this->htmlKeys, true)) {
            $value = HtmlValue::mark($value);
        }

        return $this->cache[$key] = $value;
    }

    protected function rawValue(string $key): mixed
    {
        if (is_array($this->source)) {
            return $this->source[$key] ?? null;
        }
        if ($this->source instanceof Values) {
            // Not offsetGet: it discards the Value and with it the fieldtype
            // wrap() needs to recognize markdown/bard nested in grids/sets.
            return $this->source->getProxiedInstance()->get($key);
        }

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
            return array_keys($this->source->toRawArray());
        }

        $this->augmented ??= $this->source->augmented();

        // keys() lives on AbstractAugmented, not the Augmented contract.
        // @phpstan-ignore method.notFound
        return $this->augmented->keys();
    }

    public function source(): Augmentable|Values|array
    {
        return $this->source;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function wrapAll(array $data): array
    {
        foreach ($data as $key => $value) {
            $data[$key] = static::wrapTopLevel($value);
        }

        return $data;
    }

    /**
     * Defer non-empty relationship Values so their query only runs if the
     * template touches them; everything else wraps eagerly to keep Latte's
     * scalar and truthiness semantics.
     */
    protected static function wrapTopLevel(mixed $value): mixed
    {
        // empty() is safe: relationship raws are IDs/handles, never 0 or '0'.
        if ($value instanceof Value
            && $value->isRelationship()
            && ! empty($value->raw())
        ) {
            return new Deferred($value);
        }

        return static::wrap($value);
    }

    /**
     * Normalize Statamic data into a predictable template shape: augmented
     * things and keyed maps -> Content, lists -> plain array, HTML fields ->
     * HtmlValue, scalars untouched.
     */
    public static function wrap(mixed $value): mixed
    {
        if ($value instanceof Value) {
            return static::wrapValue($value);
        }
        if ($value instanceof ArrayableString) {
            $wrapped = new ArrayableValue($value);

            // Objects are always truthy; keep falsy values scalar for Latte conditionals.
            return $wrapped->toBool() ? $wrapped : $wrapped->scalar();
        }
        if (Compare::isQueryBuilder($value)) {
            return static::wrap($value->get());
        }

        if ($value instanceof Augmentable || $value instanceof Values) {
            return new self($value);
        }

        if ($value instanceof AugmentedCollection || $value instanceof LaravelCollection) {
            return static::wrapArray($value->all());
        }
        if (is_array($value)) {
            return static::wrapArray($value);
        }

        return $value;
    }

    /**
     * Augment a Value and wrap it, marking it as safe HTML when the fieldtype
     * (not the content) says so — that's what removes the `|noescape` ritual.
     */
    protected static function wrapValue(Value $value): mixed
    {
        $augmented = $value->value();

        if (! HtmlValue::isHtmlFieldtype($value->fieldtype())) {
            return static::wrap($augmented);
        }

        // Bard with sets augments to a list of Values; only some are HTML.
        if (is_array($augmented)) {
            return static::wrapSets($augmented);
        }

        return HtmlValue::mark(static::wrap($augmented));
    }

    /**
     * Bard's synthesized `text` sets hold rendered HTML; blueprint sets are
     * ordinary fields and keep their own escaping.
     *
     * @param  array<int, mixed>  $sets
     * @return array<int, mixed>
     */
    protected static function wrapSets(array $sets): array
    {
        return array_map(
            fn ($set) => static::isBardTextSet($set)
                ? new self($set, htmlKeys: ['text'])
                : static::wrap($set),
            $sets,
        );
    }

    /**
     * A synthesized text set is exactly ['type' => 'text', 'text' => $html];
     * matching the full key signature keeps a blueprint set named `text` untrusted.
     */
    protected static function isBardTextSet(mixed $set): bool
    {
        return $set instanceof Values
            && array_keys($set->toRawArray()) === ['type', 'text']
            && $set['type'] === 'text';
    }

    /**
     * @param  array<mixed>  $array
     */
    protected static function wrapArray(array $array): mixed
    {
        if (array_is_list($array)) {
            return array_map([static::class, 'wrap'], $array);
        }

        return new self($array);
    }

    /**
     * Inverse of wrap(): peel wrappers back to raw Statamic values so they can
     * be handed to modifiers/filters, which don't understand the wrappers.
     */
    public static function unwrap(mixed $value): mixed
    {
        if ($value instanceof Content) {
            return $value->source();
        }
        if ($value instanceof Deferred) {
            return static::unwrap($value->materialize());
        }
        if ($value instanceof ArrayableValue || $value instanceof HtmlValue) {
            // HTML marking is dropped on the way out: only Statamic's own
            // augmentation is trusted to produce safe markup.
            return $value->scalar();
        }
        if (is_array($value)) {
            return array_map([static::class, 'unwrap'], $value);
        }

        return $value;
    }
}
