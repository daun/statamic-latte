<?php

namespace Daun\StatamicLatte\Latte\Support;

use Daun\StatamicLatte\Latte\Extensions\Nodes\TagNode;

/**
 * Rewrites Statamic nested tag-method syntax into a form Latte can parse.
 *
 * Latte resolves tags by exact, pre-registered name at parse time, while
 * Statamic dispatches tag methods at runtime — including arbitrary wildcard
 * methods that have no declared PHP method (e.g. {{ nav:breadcrumbs }}).
 * Those two models cannot be bridged inside Latte's compiler: its lexer and
 * parser are `final`, so there is no token- or AST-level seam to hook into.
 *
 * The one sanctioned pre-parse hook in Latte is the source loader. This class
 * is the pure, side-effect-free transformation that runs there: it lowers
 *
 *     {s:collection:count in: pages /}
 *
 * to the base tag plus an internal argument carrying the full method name
 *
 *     {s:collection __sl_tag: "collection:count", in: pages /}
 *
 * which {@see TagNode} forwards to
 * Statamic verbatim. Because the split is syntactic, every method — declared
 * or wildcard — is supported.
 */
class TagMethodSyntax
{
    /** Internal argument key used to smuggle the full `tag:method` name through. */
    public const TAG_ARGUMENT = '__sl_tag';

    /**
     * Matches an opening or closing Statamic method tag, e.g.
     * `{s:collection:count ...}` or `{/s:collection:count}`, while leaving the
     * trailing argument text untouched.
     */
    private const PATTERN = '#\{(/?)s:([A-Za-z_][A-Za-z0-9_-]*):([A-Za-z_][A-Za-z0-9_:-]*)([^{}]*)\}#';

    public static function rewrite(string $template): string
    {
        return preg_replace_callback(
            self::PATTERN,
            static function (array $matches): string {
                [, $slash, $tag, $method, $tail] = $matches;

                // Closing tags only need the base name; the method is irrelevant.
                if ($slash === '/') {
                    return "{/s:{$tag}}";
                }

                $name = "{$tag}:{$method}";
                $argument = self::TAG_ARGUMENT.': "'.$name.'"';

                return match (trim($tail)) {
                    '' => "{s:{$tag} {$argument}}",
                    '/' => "{s:{$tag} {$argument} /}",
                    default => "{s:{$tag} {$argument},{$tail}}",
                };
            },
            $template,
        ) ?? $template;
    }
}
