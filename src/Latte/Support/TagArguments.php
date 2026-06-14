<?php

namespace Daun\StatamicLatte\Latte\Support;

use Daun\StatamicLatte\Latte\Extensions\Nodes\TagNode;
use Latte\CompileException;
use Latte\Compiler\Nodes\Php\Expression\ArrayNode;
use Latte\Compiler\Nodes\Php\IdentifierNode;
use Latte\Compiler\Nodes\Php\Scalar\StringNode;
use Latte\Compiler\TagLexer;
use Latte\Compiler\TagParser;

/**
 * Parses Statamic-style tag arguments, allowing nested keys such as
 * `title:contains: foo` that Latte's own argument grammar would reject.
 *
 * Colons that sit *inside* a key are masked with a placeholder so Latte's
 * argument grammar accepts them, then restored on the parsed keys afterwards.
 *
 * Shared by the `{s:...}` tag and  the inline `(s:...)` expression form, e.g. `{var $x = (s:...)}`.
 * {@see TagNode} {@see TagExpressionSyntax}
 */
class TagArguments
{
    /** Placeholder standing in for colons inside a parameter key while Latte parses it. */
    private const COLON_PLACEHOLDER = '__sl_colon__';

    /**
     * Parse a full tag-call string such as `collection:count in: pages` into
     * its tag name (`collection:count`) and parsed parameters.
     *
     * @return array{string, ArrayNode}
     */
    public static function parse(string $text): array
    {
        $text = trim($text);

        if (! preg_match('#^([A-Za-z_][A-Za-z0-9_:-]*)(.*)$#s', $text, $matches)) {
            throw new CompileException("Invalid Statamic tag call `{$text}`.");
        }

        return [$matches[1], self::parseParams($matches[2])];
    }

    /**
     * Parse just the parameter portion (`in: pages, title:contains: foo`) into
     * an array node, restoring any masked nested-key colons.
     */
    public static function parseParams(string $text): ArrayNode
    {
        if (trim($text) === '') {
            return new ArrayNode([]);
        }

        $args = (new TagParser((new TagLexer)->tokenize(self::escapeNestedKeys($text))))->parseArguments();

        // A key written with Latte's colon syntax (`key: value`) parses to an
        // IdentifierNode, while the array fat-arrow syntax (`key => value`)
        // parses to a StringNode — handle both.
        foreach ($args->items as $item) {
            if ($item->key instanceof IdentifierNode) {
                $item->key = new IdentifierNode(self::restoreColons($item->key->name), $item->key->position);
            } elseif ($item->key instanceof StringNode) {
                $item->key = new StringNode(self::restoreColons($item->key->value), $item->key->position);
            }
        }

        return $args;
    }

    public static function restoreColons(string $key): string
    {
        return str_replace(self::COLON_PLACEHOLDER, ':', $key);
    }

    /**
     * Replace colons that sit *inside* a key with a placeholder, so Latte's
     * argument grammar sees a plain key. Colons inside quoted strings are left
     * untouched.
     *
     * A colon followed by a non-word character (whitespace, $, a quote, …) is
     * always the key/value separator. A colon followed by a word character is
     * masked only when the key continues past it — that is, when the bareword
     * segment after it is itself followed by another colon (deeper nesting,
     * `title:contains:Layout`) or by a `=>` arrow (which is the real separator,
     * `key:sub => val`). Otherwise that colon separates the key from a bareword
     * value, so `title:contains:Layout` parses like `title:contains: Layout`.
     */
    public static function escapeNestedKeys(string $text): string
    {
        $out = '';
        $quote = null;
        $length = strlen($text);

        for ($i = 0; $i < $length; $i++) {
            $char = $text[$i];

            if ($quote !== null) {
                $out .= $char;
                if ($char === $quote && $text[$i - 1] !== '\\') {
                    $quote = null;
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === ':' && self::colonContinuesKey($text, $i)) {
                $out .= self::COLON_PLACEHOLDER;

                continue;
            }

            $out .= $char;
        }

        return $out;
    }

    /**
     * Whether a colon at the given offset continues the key (mask it) rather
     * than separating the key from its value (leave it). See escapeNestedKeys.
     */
    private static function colonContinuesKey(string $text, int $i): bool
    {
        $next = $text[$i + 1] ?? '';
        if (! (ctype_alnum($next) || $next === '_')) {
            return false;
        }

        $length = strlen($text);

        // Skip the bareword segment that follows this colon.
        $j = $i + 1;
        while ($j < $length && (ctype_alnum($text[$j]) || $text[$j] === '_')) {
            $j++;
        }

        // Skip any whitespace before the next significant character.
        while ($j < $length && ctype_space($text[$j])) {
            $j++;
        }

        $after = $text[$j] ?? '';

        // Another colon means deeper key nesting; a `=` begins a `=>` arrow that
        // is itself the separator. Either way this colon stays part of the key.
        return $after === ':' || $after === '=';
    }
}
