<?php

namespace Daun\StatamicLatte\Latte\Support;

use Latte\CompileException;
use Latte\Compiler\PrintContext;

/**
 * Rewrites the inline Statamic tag-call expression `(s:[tag] ...)` into an
 * equivalent `s(...)` function call, so a tag's output can be used anywhere
 * Latte accepts an expression.
 *
 *     {var $count = (s:collection:count in: pages)}
 *     {if (s:collection:count in: pages) > 5}
 *     {(s:nav:breadcrumbs)|noescape}
 *
 * each lower to a call to the registered `s()` function, e.g.
 *
 *     s('collection:count', ['in' => 'pages'])
 *
 * Like {@see TagMethodSyntax}, this runs in the source loader: Latte's lexer
 * and parser are `final`, so the loader is the only seam where the colon-laden
 * Statamic syntax (which Latte's grammar rejects) can be reconciled.
 *
 * Parameters are parsed with {@see TagArguments} — the same machinery the
 * {@see \Daun\StatamicLatte\Latte\Extensions\Nodes\TagNode} uses — and printed
 * back to source, so nested keys, variables and barewords behave identically to
 * the block-tag form.
 *
 * The rewrite is a catch-all: every structurally valid `(s:<tag> ...)` is
 * lowered, regardless of whether the tag is currently registered. Resolution
 * happens at runtime in `s()` → `Statamic::tag()`, which throws a
 * `TagNotFoundException` for an unknown tag. This deliberately avoids baking a
 * compile-time tag-registry snapshot into cached templates, so tags added or
 * removed at runtime are always honoured. The flip side is that `(s:<identifier>
 * ...)` is reserved syntax — do not write it as literal text.
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

    /**
     * Given the index of an opening `(`, return the index just past its
     * matching `)`, honouring nested parens and quoted strings; null if
     * unbalanced.
     */
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

    /**
     * Turn the contents of a `(...)` group into an `s(...)` call, or null if it
     * is not a Statamic tail (`s:<tag>`). Whether the tag actually exists is a
     * runtime concern, resolved by `s()`.
     */
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

        // A filter applied to a param value is only parsed when it is wrapped
        // in parentheses (`in: ($x|lower)`). A bare pipe (`in: $x|lower`) is
        // silently swallowed by Latte's argument grammar, so reject it loudly
        // rather than dropping the filter.
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

    /**
     * Whether the text contains a filter pipe at the top level — i.e. a single
     * `|` outside any parentheses, brackets, braces or quoted string (and not
     * part of a `||` operator).
     */
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
