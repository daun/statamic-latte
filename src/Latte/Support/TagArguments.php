<?php

namespace Daun\StatamicLatte\Latte\Support;

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
 * Shared by {@see \Daun\StatamicLatte\Latte\Extensions\Nodes\TagNode} (the
 * `{s:...}` tag) and {@see \Daun\StatamicLatte\Latte\Extensions\Nodes\VarNode}
 * (the `{var $x = (s:...)}` assignment).
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
     * Replace colons that sit *inside* a key with a placeholder. A colon only
     * continues the key when followed by a word character ([A-Za-z0-9_]);
     * anything else (whitespace, $, a quote, etc.) ends the key name and marks
     * the start of the value. Colons inside quoted strings are left untouched.
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
            } elseif ($char === ':' && isset($text[$i + 1]) && (ctype_alnum($text[$i + 1]) || $text[$i + 1] === '_')) {
                $out .= self::COLON_PLACEHOLDER;

                continue;
            }

            $out .= $char;
        }

        return $out;
    }
}
