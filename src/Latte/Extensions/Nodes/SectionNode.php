<?php

namespace Daun\StatamicLatte\Latte\Extensions\Nodes;

use Latte\Compiler\Nodes\AreaNode;
use Latte\Compiler\Nodes\Php\ExpressionNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;

/**
 * {section 'name'} ... {/section}
 *
 * Captures its body and stores it under the given name, retrievable from any
 * engine via {yield} / {{ yield:name }} / @yield.
 */
final class SectionNode extends StatementNode
{
    public ExpressionNode $name;

    public AreaNode $content;

    /** @return \Generator<int, AreaNode|null> */
    public static function create(Tag $tag): \Generator
    {
        $node = $tag->node = new self;
        $node->name = $tag->parser->parseUnquotedStringOrExpression();
        [$node->content] = yield;

        return $node;
    }

    public function print(PrintContext $context): string
    {
        return $context->format(
            <<<'XX'
                ob_start(fn() => '');
                %node
                \Daun\StatamicLatte\Latte\Support\Sections::store(%node, ob_get_clean()) %line;
                XX,
            $this->content,
            $this->name,
            $this->position,
        );
    }

    public function &getIterator(): \Generator
    {
        yield $this->name;
        yield $this->content;
    }
}
