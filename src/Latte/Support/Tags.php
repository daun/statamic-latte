<?php

namespace Daun\StatamicLatte\Latte\Support;

use Daun\StatamicLatte\Data\Content;
use Daun\StatamicLatte\Data\Resolver;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Str;
use Statamic\Facades\Blink;
use Statamic\Facades\Cascade;
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
     * @return mixed
     */
    public static function fetch(string $name, ...$args)
    {
        return static::run($name, null, $args);
    }

    /**
     * Fetch a Statamic tag, handing it a rendered string as its tag-pair body,
     * for content-consuming tags like `widont` or `obfuscate`.
     *
     * @return mixed
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

        $tag = Statamic::tag($name)
            ->params($params)
            ->context(Cascade::instance()->toArray());

        if ($content !== null) {
            $tag = $tag->withContent($content);
        }

        return static::fetchTag($name, $tag);
    }

    /**
     * Run a configured Statamic tag and normalize its output.
     *
     * @param  FluentTag|mixed  $tag
     * @return mixed
     */
    protected static function fetchTag(string $name, $tag)
    {
        // Statamic stashes paginators in Blink (GetsQueryResults). Snapshot the
        // shared slot so only a paginator created by this tag is recovered.
        /** @var mixed $previousPaginator */
        $previousPaginator = Blink::get('tag-paginator');

        try {
            $result = $tag->fetch();
        } catch (\BadMethodCallException $e) {
            throw self::invalidTagMethod($name, $e);
        }

        /** @var mixed $paginator */
        $paginator = Blink::get('tag-paginator');

        if ($paginator instanceof AbstractPaginator && $paginator !== $previousPaginator) {
            return static::normalizePaginator($paginator);
        }

        // Normalize output to the same Content/array shapes as view data.
        return Content::wrap($result);
    }

    /**
     * Stringify a self-closing tag result: scalars/Stringables print, booleans
     * don't, wrappers are drilled first, anything else prints nothing.
     */
    public static function stringifyResult(mixed $result): string
    {
        if (($value = self::printableValue($result)) !== null) {
            return $value;
        }

        return self::printableValue(Resolver::actual(Content::unwrap($result))) ?? '';
    }

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

    /** Friendlier exception for an unknown tag method like `{s:users:count}`. */
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

    /** Normalize a paginator's items in place, keeping its pagination API usable. */
    protected static function normalizePaginator(AbstractPaginator $paginator): AbstractPaginator
    {
        $items = $paginator->getCollection()->map(
            fn ($item) => Content::wrap($item)
        );

        return $paginator->setCollection($items);
    }
}
