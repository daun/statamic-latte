<?php

namespace Daun\StatamicLatte\Latte\Support;

use Daun\StatamicLatte\Data\Content;
use Daun\StatamicLatte\Data\Resolver;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Str;
use Statamic\Facades\Blink;
use Statamic\Statamic;
use Statamic\Tags\FluentTag;

class Tags
{
    public const PREFIX = 's:';

    public static function prefix(string $name): string
    {
        return self::PREFIX.$name;
    }

    public static function unprefix(string $name): string
    {
        return Str::replaceStart(self::PREFIX, '', $name);
    }

    public static function equals(string $name, string $check): bool
    {
        return static::unprefix($name) === static::unprefix($check);
    }

    /**
     * Fetch the output of a Statamic tag.
     *
     * @param  string  $name  The tag name
     * @param  mixed  ...$args  The tag parameters
     * @return mixed The tag output
     */
    public static function fetch(string $name, ...$args)
    {
        return static::run($name, null, $args);
    }

    /**
     * Fetch the output of a Statamic tag, handing it a rendered string as its
     * tag-pair body (e.g. `{s:widont content: $text/}`). Lets content-consuming
     * tags such as `widont`, `obfuscate` or addon tags like `mjml` work.
     *
     * @param  string  $name  The tag name
     * @param  string  $content  The rendered tag-pair body
     * @param  mixed  ...$args  The tag parameters
     * @return mixed The tag output
     */
    public static function fetchWithContent(string $name, string $content, ...$args)
    {
        return static::run($name, $content, $args);
    }

    /**
     * @param  array<mixed>  $args
     * @return mixed
     */
    protected static function run(string $name, ?string $content, array $args)
    {
        $params = $args;

        // Allow passing in params as a single array argument
        foreach ($args as $key => $arg) {
            if (is_int($key) && is_array($arg) && ! array_is_list($arg)) {
                $params = array_merge($params, $arg);
                unset($params[$key]);
            }
        }

        $tag = Statamic::tag($name)->params($params);

        if ($content !== null) {
            $tag = $tag->withContent($content);
        }

        return static::fetchTag($name, $tag);
    }

    /**
     * Run a configured Statamic tag and normalize its output, rethrowing an
     * unknown tag-method call as a friendlier exception.
     *
     * @param  FluentTag|mixed  $tag
     * @return mixed
     */
    protected static function fetchTag(string $name, $tag)
    {
        // Statamic flattens a paginated query into a plain array, discarding the
        // paginator itself. It does stash the original paginator in Blink first
        // (see GetsQueryResults::paginatedResults), so we forget any stale slot,
        // run the tag, and recover the real Laravel paginator if one was set.
        // That keeps `{foreach}`, `$p->total()`, `$p->links()` etc. idiomatic.
        Blink::forget('tag-paginator');

        try {
            $result = $tag->fetch();
        } catch (\BadMethodCallException $e) {
            throw self::invalidTagMethod($name, $e);
        }

        /** @var mixed $paginator */
        $paginator = Blink::get('tag-paginator');

        if ($paginator instanceof AbstractPaginator) {
            Blink::forget('tag-paginator');

            return static::normalizePaginator($paginator);
        }

        // Normalize tag output to the same Content/array shapes as view data,
        // so {foreach s('collection:pages') as $entry}{$entry->title} works.
        return Content::wrap($result);
    }

    /**
     * Stringify a self-closing / empty-body tag result for output.
     *
     * Scalars and Stringables print directly; booleans never print; Content
     * and other wrappers are drilled to their underlying value and printed
     * only if that resolves to a scalar/Stringable. Anything else prints
     * nothing instead of fataling on a non-stringable object.
     */
    public static function stringifyResult(mixed $result): string
    {
        if (($value = self::printableValue($result)) !== null) {
            return $value;
        }

        // Drill into Content / augmented wrappers, then retry.
        return self::printableValue(Resolver::actual(Content::unwrap($result))) ?? '';
    }

    /**
     * Cast a value to its printable string, or null if it is not printable.
     */
    private static function printableValue(mixed $value): ?string
    {
        if (is_bool($value)) {
            return '';
        }

        if (is_scalar($value) || $value instanceof \Stringable) {
            return (string) $value;
        }

        return null;
    }

    /**
     * Build a friendlier exception for a call to an unknown tag method such
     * as `{s:users:count}`, preserving the original as the previous exception.
     */
    private static function invalidTagMethod(string $name, \BadMethodCallException $e): \BadMethodCallException
    {
        [$tag, $method] = array_pad(explode(':', $name, 2), 2, null);

        if ($method === null) {
            return $e;
        }

        return new \BadMethodCallException(
            "{s:{$name}}: '{$method}' is not a valid method of the {$tag} tag.",
            0,
            $e,
        );
    }

    /**
     * Normalize a paginator's items in place, leaving the paginator itself
     * intact so its pagination API (total, currentPage, links, …) stays usable.
     */
    protected static function normalizePaginator(AbstractPaginator $paginator): AbstractPaginator
    {
        $items = $paginator->getCollection()->map(
            fn ($item) => Content::wrap($item)
        );

        return $paginator->setCollection($items);
    }
}
