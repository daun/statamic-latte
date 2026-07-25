<?php

namespace Daun\StatamicLatte\Latte\Support;

use Latte\CompileException;
use Latte\Compiler\PrintContext;

/**
 * Rewrites the inline tag-call expression `(s:tag ...)` into an `s(...)` call
 * in the source loader — Latte's lexer/parser are final, so the loader is the
 * only seam for the colon-laden Statamic syntax.
 *
 * The rewrite is a catch-all: every structurally valid `(s:<tag> ...)` is
 * lowered, registered or not, so no tag-registry snapshot is baked into cached
 * templates. Flip side: `(s:<identifier> ...)` is reserved syntax.
 */
class TagExpressionSyntax
{
    public static function rewrite(string $template): string
    {
        $out = '';
        $length = strlen($template);
        $i = 0;

        while ($i < $length) {
            if ($template[$i] === '(' && ($end = self::matchParen($template, $i)) !== null) {
                $inner = substr($template, $i + 1, $end - $i - 2);
                if (($call = self::rewriteCall($inner)) !== null) {
                    $out .= $call;
                    $i = $end;

                    continue;
                }
            }

            $out .= $template[$i];
            $i++;
        }

        return $out;
    }

    /** Index just past the matching `)`, honouring nesting and quotes; null if unbalanced. */
    private static function matchParen(string $s, int $open): ?int
    {
        $depth = 0;
        $quote = null;
        $length = strlen($s);

        for ($i = $open; $i < $length; $i++) {
            $char = $s[$i];

            if ($quote !== null) {
                if ($char === $quote && $s[$i - 1] !== '\\') {
                    $quote = null;
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '(') {
                $depth++;
            } elseif ($char === ')' && --$depth === 0) {
                return $i + 1;
            }
        }

        return null;
    }

    /** Turn a `(...)` group into an `s(...)` call, or null if it's not an `s:` tail. */
    private static function rewriteCall(string $inner): ?string
    {
        $trimmed = ltrim($inner);
        if (! preg_match('#^s:[A-Za-z_]#', $trimmed)) {
            return null;
        }

        $call = substr($trimmed, 2);

        try {
            [$name, $args] = TagArguments::parse($call);
        } catch (\Throwable) {
            return null;
        }

        // A bare pipe (`in: $x|lower`) is silently swallowed by Latte's argument
        // grammar, so reject it loudly rather than dropping the filter.
        if (self::hasTopLevelPipe($call)) {
            throw new CompileException(
                "Bare filters are not supported inside `(s:{$call})`. "
                .'Wrap the filtered value in parentheses, e.g. `in: ($x|lower)`; '
                .'to filter the tag result, place the filter outside: `(s:...)|upper`.'
            );
        }

        $params = $args->print(new PrintContext);

        return $params === '[]'
            ? "(s('{$name}'))"
            : "(s('{$name}', {$params}))";
    }

    /** A single `|` outside parens/brackets/quotes that isn't part of `||`. */
    private static function hasTopLevelPipe(string $s): bool
    {
        $depth = 0;
        $quote = null;
        $length = strlen($s);

        for ($i = 0; $i < $length; $i++) {
            $char = $s[$i];

            if ($quote !== null) {
                if ($char === $quote && $s[$i - 1] !== '\\') {
                    $quote = null;
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '(' || $char === '[' || $char === '{') {
                $depth++;
            } elseif ($char === ')' || $char === ']' || $char === '}') {
                $depth--;
            } elseif ($char === '|' && $depth === 0
                && ($s[$i - 1] ?? '') !== '|' && ($s[$i + 1] ?? '') !== '|'
            ) {
                return true;
            }
        }

        return false;
    }
}
