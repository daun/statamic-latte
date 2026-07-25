<?php

namespace Daun\StatamicLatte\Latte\Support;

use Daun\StatamicLatte\Data\Content;
use Daun\StatamicLatte\Data\Resolver;
use Daun\StatamicLatte\Latte\Extensions\Nodes\HrefNode;
use Latte\Runtime\HtmlHelpers;
use Statamic\Facades\URL;

/**
 * Runtime backing for the {@see HrefNode n:href}
 * attribute: turns any linkable value into an `href` plus the companion
 * attributes a link usually wants.
 *
 * `<a n:href="$link">` emits, in priority order:
 *   1. `href="…"` — always, for any non-empty URL.
 *   2. external URL (different host)  -> `target="_blank" rel="noopener"`
 *   3. the current page              -> `aria-current="page"`
 *   4. an ancestor of the current page -> `aria-current="true"`
 *
 * A null/empty URL renders nothing at all, so optional links need no outer
 * {if}. `mailto:`/`tel:` links get only their `href` — they have no host to be
 * "external" and no path to be "current".
 */
class Href
{
    /**
     * Build the attribute string for `<a n:href="$value">`, leading space
     * included, ready to echo raw inside the opening tag.
     *
     * @param  list<string>  $skip  Companion attributes the element already
     *                              carries, which this must not emit again.
     */
    public static function attrs(mixed $value, array $skip = []): string
    {
        $url = static::resolve($value);

        if ($url === null || $url === '') {
            return '';
        }

        $attrs = ' href="'.HtmlHelpers::escapeAttr($url).'"';

        if (static::isExternal($url)) {
            if (! in_array('target', $skip, true)) {
                $attrs .= ' target="_blank"';
            }
            if (! in_array('rel', $skip, true)) {
                $attrs .= ' rel="noopener"';
            }
        } elseif (! in_array('aria-current', $skip, true)) {
            if (static::isCurrent($url)) {
                $attrs .= ' aria-current="page"';
            } elseif (static::isAncestor($url)) {
                $attrs .= ' aria-current="true"';
            }
        }

        return $attrs;
    }

    /**
     * Resolve any linkable value to a URL string, or null when there is none.
     *
     * Accepts a plain string, a Statamic routable/linkable object (Entry, Term,
     * Asset, Site, a link field's ArrayableLink — anything exposing url()), and
     * the wrappers those arrive in at the render boundary (Content, Deferred,
     * augmented Value, ArrayableValue).
     */
    public static function resolve(mixed $value): ?string
    {
        // Peel Latte wrappers to the raw Statamic source, then let the resolver
        // unwrap any augmented Value / query builder behind it.
        $value = Resolver::actual(Content::unwrap($value));

        if (is_string($value)) {
            return $value !== '' ? $value : null;
        }

        // Entry, Term, Asset, Site, ArrayableLink, redirect entries, ...
        if (is_object($value) && method_exists($value, 'url')) {
            $url = $value->url();

            return is_string($url) && $url !== '' ? $url : null;
        }

        return null;
    }

    /**
     * Is this an external URL — an absolute http(s) URL on a different host than
     * the current site? Only http(s) URLs qualify, so `mailto:`/`tel:` and other
     * schemes (which have no host to compare, and no meaningful `target`) keep
     * just their `href`.
     */
    protected static function isExternal(string $url): bool
    {
        return URL::isAbsolute($url) && URL::isExternal($url);
    }

    /**
     * Is this URL the current page? Compared as relative paths with trailing
     * slashes normalized away, so `/about` and `/about/` are the same page.
     */
    protected static function isCurrent(string $url): bool
    {
        return static::path($url) === static::path(URL::getCurrent());
    }

    /**
     * Is this URL an ancestor of the current page? The site root is an ancestor
     * of everything, so it is explicitly excluded.
     */
    protected static function isAncestor(string $url): bool
    {
        if (static::path($url) === '') {
            return false;
        }

        return URL::isAncestorOf(URL::getCurrent(), URL::makeRelative($url));
    }

    /**
     * Normalize a URL to a relative path without its trailing slash, for
     * equality comparison.
     */
    protected static function path(string $url): string
    {
        return rtrim(URL::makeRelative($url), '/');
    }
}
