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
 * Lazy object wrapper around a single keyed source: an augmented Statamic
 * value-set (Entry/Asset/Term, Values group/grid row) or a plain associative
 * array/map.
 *
 * Property access resolves exactly one key on demand:
 *   {$entry->title}          -> augmentedValue('title')->value()
 *   {$entry->author->name}   -> nested Content, augmented lazily
 *
 * Supports both `->key` and `['key']` so a template never guesses wrong.
 *
 * Method calls pass through to the underlying source object so custom
 * entry classes can expose logic to templates ({$page->events()}), with
 * return values wrapped and destructive methods (save/delete/...) blocked.
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
    /**
     * @var array<int, string>
     */
    protected const GUARDED_METHODS = [
        'delete', 'deletequietly',
        'save', 'savequietly',
        'set', 'setsupplement', 'remove', 'removesupplement', 'merge',
        'move', 'rename', 'replace', 'reupload',
    ];

    /** @var array<string, mixed> Normalized per-key cache. */
    protected array $cache = [];

    protected ?Augmented $augmented = null;

    /**
     * @param  Augmentable|Values|array<string, mixed>  $source
     * @param  list<string>  $htmlKeys  Keys holding rendered HTML, when the
     *                                  source itself knows better than the
     *                                  fieldtype (see {@see wrapSets()}).
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
            // Read the underlying Value off the proxied collection rather than
            // through offsetGet: both augment, but offsetGet discards the Value
            // afterwards, and with it the fieldtype wrap() needs to recognize
            // markdown/bard fields nested in grids, replicators and sets.
            return $this->source->getProxiedInstance()->get($key);
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
            return array_keys($this->source->toRawArray());
        }

        $this->augmented ??= $this->source->augmented();

        // keys() lives on AbstractAugmented, not the Augmented contract.
        // @phpstan-ignore method.notFound
        return $this->augmented->keys();
    }

    /** Escape hatch: get the underlying source. */
    public function source(): Augmentable|Values|array
    {
        return $this->source;
    }

    /**
     * Normalize a bag of template variables into template shapes, leaving
     * framework internals alone.
     *
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
     * Top-level wrap with relationship deferral.
     *
     * A non-empty relationship Value is postponed behind a {@see Deferred}
     * proxy so its query + augmentation only runs if the template touches it.
     * Everything else (scalars, non-relationship Values like markdown/bard,
     * empty relationships) is wrapped eagerly so Latte keeps correct scalar
     * and truthiness semantics. Nested access stays lazy via Content already,
     * so deferral is only needed at this top level.
     */
    protected static function wrapTopLevel(mixed $value): mixed
    {
        // empty() is exact here: relationship raw values are entry/term/asset
        // IDs (UUIDs or handles), never 0 or '0', so the empty('0')/empty(0)
        // false-positive cannot occur. An empty/null raw means no relations.
        if ($value instanceof Value
            && $value->isRelationship()
            && ! empty($value->raw())
        ) {
            return new Deferred($value);
        }

        return static::wrap($value);
    }

    /**
     * Normalize Statamic data into a predictable template shape:
     *   - single augmented thing (Entry/Asset/Term, Values group/grid row) -> Content
     *   - associative map (keyed collection / keyed array)                 -> Content
     *   - sequential list (list collection / list array)                  -> plain array
     *   - HTML-bearing field (markdown, bard, ...)                        -> HtmlValue
     *   - scalars / unknown objects                                       -> untouched
     */
    public static function wrap(mixed $value): mixed
    {
        // Unwrap lazy single values first.
        if ($value instanceof Value) {
            return static::wrapValue($value);
        }
        if ($value instanceof ArrayableString) {
            $wrapped = new ArrayableValue($value);

            // PHP objects are always truthy. Keep falsy values scalar so
            // Latte conditionals retain their existing behavior.
            return $wrapped->toBool() ? $wrapped : $wrapped->scalar();
        }
        if (Compare::isQueryBuilder($value)) {
            return static::wrap($value->get());
        }

        // Single augmented object -> Content wrapper (object semantics).
        if ($value instanceof Augmentable || $value instanceof Values) {
            return new self($value);
        }

        // Collections + arrays: shape decides. List -> array, keyed -> object.
        if ($value instanceof AugmentedCollection || $value instanceof LaravelCollection) {
            return static::wrapArray($value->all());
        }
        if (is_array($value)) {
            return static::wrapArray($value);
        }

        return $value;
    }

    /**
     * Augment a Value and wrap the result, marking it as safe HTML when the
     * field is one Statamic renders markup for (markdown, bard, ...).
     *
     * The fieldtype decides, not the content: a text field is escaped even if
     * it happens to hold `<em>`, and an HTML field is trusted even if it holds
     * none. That's the whole point — it removes the `|noescape` ritual without
     * ever guessing at a string.
     */
    protected static function wrapValue(Value $value): mixed
    {
        $augmented = $value->value();

        if (! HtmlValue::isHtmlFieldtype($value->fieldtype())) {
            return static::wrap($augmented);
        }

        // A bard field configured with sets augments to a list of Values
        // instead of one HTML string. Only some of those are HTML.
        if (is_array($augmented)) {
            return static::wrapSets($augmented);
        }

        return HtmlValue::mark(static::wrap($augmented));
    }

    /**
     * Wrap the sets of a bard field.
     *
     * Bard splits its content into the sets defined in the blueprint plus the
     * rendered markup between them, which it hands over as synthesized `text`
     * sets. Those are HTML; the blueprint sets are ordinary fields and keep
     * their own escaping (their nested markdown/bard fields are recognized on
     * access, by fieldtype, like anywhere else).
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
     * Is this one of bard's own text sets?
     *
     * A synthesized text set is exactly `['type' => 'text', 'text' => $html]`.
     * Matching the full key signature — not just the type — keeps a
     * blueprint set that happens to be named `text` from being trusted.
     */
    protected static function isBardTextSet(mixed $set): bool
    {
        return $set instanceof Values
            && array_keys($set->toRawArray()) === ['type', 'text']
            && $set['type'] === 'text';
    }

    /**
     * Sequential list -> plain array of wrapped children (iterable).
     * Associative map -> Content object (lazy, `->`/`[]` access).
     *
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
     * Inverse of wrap(): peel Content wrappers back to their raw Statamic
     * sources so values can be handed to Statamic modifiers/filters, which
     * predate (and don't understand) the Content wrapper.
     */
    public static function unwrap(mixed $value): mixed
    {
        if ($value instanceof Content) {
            return $value->source();
        }
        if ($value instanceof Deferred) {
            // Materialize then unwrap: modifiers, n:attr and Antlers all expect
            // a plain array / augmentable, never a proxy object.
            return static::unwrap($value->materialize());
        }
        if ($value instanceof ArrayableValue || $value instanceof HtmlValue) {
            // Preserve the scalar value modifiers and other reverse boundaries
            // received before these wrappers existed. HTML marking is dropped
            // on the way out: what a modifier returns is its own output, and
            // only Statamic's augmentation is trusted to produce safe markup.
            return $value->scalar();
        }
        if (is_array($value)) {
            return array_map([static::class, 'unwrap'], $value);
        }

        return $value;
    }
}
