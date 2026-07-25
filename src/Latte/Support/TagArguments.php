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
 * Parses Statamic-style tag arguments, allowing nested keys like
 * `title:contains: foo` that Latte's argument grammar would reject: colons
 * inside a key are masked with a placeholder, then restored after parsing.
 * Shared by the `{s:...}` tag (TagNode) and the inline `(s:...)` expression.
 */
class TagArguments
{
    private const COLON_PLACEHOLDER = '__sl_colon__';

    /**
     * Split `collection:count in: pages` into tag name and parsed params.
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

    public static function parseParams(string $text): ArrayNode
    {
        if (trim($text) === '') {
            return new ArrayNode([]);
        }

        $args = (new TagParser((new TagLexer)->tokenize(self::escapeNestedKeys($text))))->parseArguments();

        // `key: value` parses to an IdentifierNode, `key => value` to a StringNode.
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
     * Mask colons inside keys with a placeholder; quoted strings are untouched.
     * A colon before a word character is only masked when the key continues
     * past it (another colon or a `=>` follows the bareword) — otherwise it
     * separates the key from a bareword value: `title:contains:Layout` parses
     * like `title:contains: Layout`.
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

    /** Whether the colon at offset $i continues the key rather than starting the value. */
    private static function colonContinuesKey(string $text, int $i): bool
    {
        $next = $text[$i + 1] ?? '';
        if (! (ctype_alnum($next) || $next === '_')) {
            return false;
        }

        $length = strlen($text);

        // Skip the following bareword and any whitespace after it.
        $j = $i + 1;
        while ($j < $length && (ctype_alnum($text[$j]) || $text[$j] === '_')) {
            $j++;
        }
        while ($j < $length && ctype_space($text[$j])) {
            $j++;
        }

        $after = $text[$j] ?? '';

        // Another colon = deeper nesting; `=` begins the real `=>` separator.
        return $after === ':' || $after === '=';
    }
}
