<?php

namespace Daun\StatamicLatte\Latte\Extensions\Nodes;

use Daun\StatamicLatte\Latte\Support\Href;
use Latte\CompileException;
use Latte\Compiler\Nodes\Php\ExpressionNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;

/**
 * <a n:href="$link"> — emits an `href` for any linkable value plus target/rel/
 * aria-current where appropriate (see Href). Combining with a literal `href`
 * throws; writing a companion attribute yourself makes n:href skip that one.
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

        // Companion attributes the template writes itself win; never duplicate them.
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
