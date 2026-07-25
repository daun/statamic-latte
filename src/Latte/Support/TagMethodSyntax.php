<?php

namespace Daun\StatamicLatte\Latte\Support;

/**
 * Rewrites `{s:collection:count ...}` into `{s:collection __sl_tag: "collection:count", ...}`
 * in the source loader — Latte registers tags by exact name at parse time, and
 * its lexer/parser are final, so the loader is the only seam. Because the split
 * is syntactic, every tag method — declared or wildcard — is supported.
 */
class TagMethodSyntax
{
    /** Internal argument key smuggling the full `tag:method` name through. */
    public const TAG_ARGUMENT = '__sl_tag';

    /** Opening or closing Statamic method tag, leaving the argument tail untouched. */
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
