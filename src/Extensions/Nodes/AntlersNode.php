<?php

namespace Daun\StatamicLatte\Extensions\Nodes;

use Latte\CompileException;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;
use Latte\Compiler\TemplateParser;

/**
 * {antlers} {/antlers}
 */
class AntlersNode extends StatementNode
{
    use Traits\ExtractsToTemporaryView;

    protected $viewFileExtension = 'antlers.html';

    /** @return \Generator<int, ?array, array{AreaNode, ?Tag}, static> */
    public static function create(Tag $tag, TemplateParser $parser): \Generator
    {
        $node = $tag->node = new static;
        if (! $tag->parser->isEnd()) {
            throw new CompileException("Unexpected arguments in {$tag->getNotation()}", $tag->position);
        }

        // Read inner content as raw text
        static::disableParserForTag($tag, $parser);
        [$node->content] = yield;
        static::restoreParserForTag($tag, $parser);

        return $node;
    }

    public function print(PrintContext $context): string
    {
        return $context->format(
            'echo view(%dump, ["__layout_parent" => $this->getName()] + get_defined_vars())->render() %line;',
            $this->saveContentToView(),
            $this->position
        );
    }
}
