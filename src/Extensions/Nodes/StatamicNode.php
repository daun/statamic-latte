<?php

namespace Daun\StatamicLatte\Extensions\Nodes;

use Latte\CompileException;
use Latte\Compiler\Nodes\AreaNode;
use Latte\Compiler\Nodes\Php\Expression\ArrayNode;
use Latte\Compiler\Nodes\Php\ExpressionNode;
use Latte\Compiler\Nodes\Php\Scalar\StringNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;
use Latte\Compiler\TemplateParser;

/**
 * {statamic tag [,] [params]}
 * {tag [,] [params]}
 */
final class StatamicNode extends StatementNode
{
    public ExpressionNode $tag;

    public ArrayNode $args;

    public ?AreaNode $content;

    public bool $isPair;

    /** @return \Generator<int, AreaNode|null> */
    public static function create(Tag $tag, TemplateParser $parser): \Generator
    {
        if ($tag->isNAttribute()) {
            throw new CompileException('Attribute n:statamic is not supported.', $tag->position);
        }

        $node = new self;
        if ($tag->name === 'statamic') {
            $tag->expectArguments();
            $node->tag = $tag->parser->parseUnquotedStringOrExpression();
        } else {
            $node->tag = new StringNode($tag->name, $tag->position);
        }

        $tag->parser->stream->tryConsume(',');
        $node->args = $tag->parser->parseArguments();

        [$node->content] = yield;

        return $node;
    }

    public function print(PrintContext $context): string
    {
        return $context->format(
            <<<'XX'
                $result = Statamic::tag(%node) %line
                    ->context(get_defined_vars())
                    ->params(%node)
                    ->fetch();
                ray(%0.node, $result);
                echo $result;
                %node
                XX,
            $this->tag,
            $this->position,
            $this->args,
            $this->content
        );
    }

    public function &getIterator(): \Generator
    {
        yield $this->tag;
        yield $this->args;
        yield $this->content;
    }
}
