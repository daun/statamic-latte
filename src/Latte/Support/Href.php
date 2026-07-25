<?php

namespace Daun\StatamicLatte\Latte\Support;

use Daun\StatamicLatte\Data\Content;
use Daun\StatamicLatte\Data\Resolver;
use Daun\StatamicLatte\Latte\Extensions\Nodes\HrefNode;
use Latte\Runtime\HtmlHelpers;
use Statamic\Facades\URL;

/**
 * Runtime backing for n:href (see HrefNode): turns any linkable value into an
 * `href` plus target/rel for external links and aria-current for the current
 * page or its ancestors. A null/empty URL renders no attributes at all.
 */
class Href
{
    /**
     * Attribute string for `<a n:href="$value">`, leading space included.
     *
     * @param  list<string>  $skip  Attributes the element already carries.
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
     * Resolve a linkable value — string, anything exposing url(), or the
     * wrappers those arrive in — to a URL string, or null when there is none.
     */
    public static function resolve(mixed $value): ?string
    {
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

    /** Only absolute http(s) URLs qualify, so mailto:/tel: keep just their href. */
    protected static function isExternal(string $url): bool
    {
        return URL::isAbsolute($url) && URL::isExternal($url);
    }

    protected static function isCurrent(string $url): bool
    {
        return static::path($url) === static::path(URL::getCurrent());
    }

    protected static function isAncestor(string $url): bool
    {
        // The site root is an ancestor of everything; exclude it.
        if (static::path($url) === '') {
            return false;
        }

        return URL::isAncestorOf(URL::getCurrent(), URL::makeRelative($url));
    }

    /** Relative path without trailing slash, so /about and /about/ compare equal. */
    protected static function path(string $url): string
    {
        return rtrim(URL::makeRelative($url), '/');
    }
}
