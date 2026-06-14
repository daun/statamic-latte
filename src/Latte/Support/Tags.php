<?php

namespace Daun\StatamicLatte\Latte\Support;

use Daun\StatamicLatte\Data\Normalizer;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Str;
use Statamic\Facades\Blink;
use Statamic\Statamic;

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

        // Statamic flattens a paginated query into a plain array, discarding the
        // paginator itself. It does stash the original paginator in Blink first
        // (see GetsQueryResults::paginatedResults), so we forget any stale slot,
        // run the tag, and recover the real Laravel paginator if one was set.
        // That keeps `{foreach}`, `$p->total()`, `$p->links()` etc. idiomatic.
        Blink::forget('tag-paginator');

        $result = $tag->fetch();

        /** @var mixed $paginator */
        $paginator = Blink::get('tag-paginator');

        if ($paginator instanceof AbstractPaginator) {
            Blink::forget('tag-paginator');

            return static::normalizePaginator($paginator);
        }

        // Normalize tag output to the same Content/array shapes as view data,
        // so {foreach s('collection:pages') as $entry}{$entry->title} works.
        return Normalizer::normalize($result);
    }

    /**
     * Normalize a paginator's items in place, leaving the paginator itself
     * intact so its pagination API (total, currentPage, links, …) stays usable.
     */
    protected static function normalizePaginator(AbstractPaginator $paginator): AbstractPaginator
    {
        $items = $paginator->getCollection()->map(
            fn ($item) => Normalizer::normalize($item)
        );

        return $paginator->setCollection($items);
    }
}
