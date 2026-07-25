<?php

namespace Daun\StatamicLatte\Latte\Extensions\Nodes;

use Latte\Compiler\Nodes\AreaNode;
use Latte\Compiler\Nodes\Php\ExpressionNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;

/**
 * {yield 'name' /} or {yield 'name'}default{/yield} — outputs a section defined
 * anywhere, in any engine. Resolution is deferred until the whole template has
 * rendered, so the section may be defined before or after its yield.
 */
final class YieldNode extends StatementNode
{
    public ExpressionNode $name;

    public ?AreaNode $default = null;

    /** @return \Generator<int, AreaNode|null> */
    public static function create(Tag $tag): \Generator
    {
        $node = $tag->node = new self;
        $node->name = $tag->parser->parseUnquotedStringOrExpression();
        if ($tag->void) {
            return $node;
        }
        [$node->default] = yield;

        return $node;
    }

    public function print(PrintContext $context): string
    {
        if ($this->default) {
            return $context->format(
                <<<'XX'
                    ob_start(fn() => '');
                    %node
                    echo \Daun\StatamicLatte\Latte\Support\Sections::placeholder(%node, ob_get_clean()) %line;
                    XX,
                $this->default,
                $this->name,
                $this->position,
            );
        }

        return $context->format(
            'echo \Daun\StatamicLatte\Latte\Support\Sections::placeholder(%node) %line;',
            $this->name,
            $this->position,
        );
    }

    public function &getIterator(): \Generator
    {
        yield $this->name;
        if ($this->default) {
            yield $this->default;
        }
    }
}
