<?php

namespace Daun\StatamicLatte\Extensions\Nodes;

use Latte\CompileException;
use Latte\Compiler\Nodes\AreaNode;
use Latte\Compiler\Nodes\Php\Expression\ArrayNode;
use Latte\Compiler\Nodes\Php\ExpressionNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;
use Latte\Compiler\TemplateParser;

/**
 * {statamic name [,] [params]}
 */
final class StatamicNode extends StatementNode
{
    public ExpressionNode $name;

    public ArrayNode $args;

    public ?AreaNode $content;

    public bool $isPair;

    /** @return \Generator<int, AreaNode|null> */
    public static function create(Tag $tag, TemplateParser $parser): \Generator
    {
        if ($tag->isNAttribute()) {
            throw new CompileException('Attribute n:statamic is not supported.', $tag->position);
        }
        $tag->expectArguments();
        $node = $tag->node = new static;
        // $node->name = $tag->name;
        $node->name = $tag->parser->parseUnquotedStringOrExpression();
        $tag->parser->stream->tryConsume(',');
        $node->args = $tag->parser->parseArguments();
        $node->isPair = ! $tag->parser->isEnd();

        [$node->content] = yield;

        return $node;
    }

    public function print(PrintContext $context): string
    {
        return $context->format(
            <<<'XX'
				echo "statamic:%dump" %line;
				%node
				$this->enterBlockLayer(%dump, get_defined_vars()) %line;
				try {
					$this->createTemplate(%node, %node, "embed")->renderToContentType(%dump) %1.line;
				} finally {
					$this->leaveBlockLayer();
				}

				XX,
            $this->name,
            $this->position,
            $this->args,
            $this->content
        );
    }

    public function &getIterator(): \Generator
    {
        yield $this->name;
        yield $this->args;
        yield $this->content;
    }
}
