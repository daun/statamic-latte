<?php

namespace Daun\StatamicLatte\Latte\Extensions\Nodes;

use Daun\StatamicLatte\Latte\Support\Href;
use Latte\CompileException;
use Latte\Compiler\Nodes\Php\ExpressionNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;

/**
 * <a n:href="$link">
 *
 * Emits an `href` for any linkable value — a URL string or a Statamic
 * routable (Entry, Term, Asset, Site, link field) — plus the companion
 * attributes links usually need: target/rel for external links, aria-current
 * for the current page and its ancestors. See {@see Href}
 * for the full behavior.
 *
 * Only valid as an n:attribute; there is nothing to write as a paired tag.
 * Combining it with a literal `href` throws — there is no sensible merge.
 * Its companion attributes bow out per-attribute: write `target`, `rel` or
 * `aria-current` on the element yourself and n:href leaves that one alone.
 */
final class HrefNode extends StatementNode
{
    /** Companion attributes a template may override by writing them itself. */
    private const OverridableAttributes = ['target', 'rel', 'aria-current'];

    public ExpressionNode $value;

    /** @var list<string> Companion attributes already on the element, to skip. */
    public array $skip = [];

    public static function create(Tag $tag): static
    {
        if (! $tag->isNAttribute()) {
            throw new CompileException('n:href is only usable as an attribute.', $tag->position);
        }

        $element = $tag->htmlElement;
        assert($element !== null);

        if ($element->getAttribute('href') !== null) {
            throw new CompileException('It is not possible to combine href with n:href.', $tag->position);
        }

        $tag->expectArguments();

        $node = new self;
        $node->value = $tag->parser->parseExpression();

        // Any companion attribute the template writes itself wins: record it so
        // the runtime leaves that one to the element and never emits a duplicate.
        foreach (self::OverridableAttributes as $name) {
            if ($element->getAttribute($name) !== null) {
                $node->skip[] = $name;
            }
        }

        return $node;
    }

    public function print(PrintContext $context): string
    {
        return $context->format(
            'echo \Daun\StatamicLatte\Latte\Support\Href::attrs(%node, %dump) %line;',
            $this->value,
            $this->skip,
            $this->position,
        );
    }

    public function &getIterator(): \Generator
    {
        yield $this->value;
    }
}
