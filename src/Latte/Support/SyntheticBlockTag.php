<?php

namespace Daun\StatamicLatte\Latte\Support;

use Latte\Compiler\Position;
use Latte\Compiler\Tag;
use Latte\Compiler\Token;

/**
 * Builds an inert `{block}` tag for blocks the addon creates or re-labels.
 *
 * The name is load-bearing: Latte's TemplateGenerator only hands an embedded
 * block the caller's variables (`end($this->varStack)`) when its tag is
 * literally called `block`. Latte's own EmbedNode relies on the same trick.
 */
class SyntheticBlockTag
{
    /**
     * Mirror an existing tag's position, `n:` prefix and argument text.
     */
    public static function from(Tag $tag): Tag
    {
        $end = property_exists(Tag::class, 'end') ? $tag->end : null;

        return static::create($tag->position, $end, $tag->prefix, $tag->parser->text);
    }

    public static function create(Position $position, ?Position $end = null, ?string $prefix = null, string $text = ''): Tag
    {
        $arguments = [
            'name' => 'block',
            'tokens' => [new Token(Token::End, '', $position)],
            'position' => $position,
            'prefix' => $prefix,
        ];

        // Latte 3.1.5 added the required `$end` position after `$position`
        if (property_exists(Tag::class, 'end')) {
            $arguments['end'] = $end ?? $position;
        }

        $tag = new Tag(...$arguments);
        $tag->parser->text = $text;

        return $tag;
    }
}
