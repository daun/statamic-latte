<?php

namespace Daun\StatamicLatte\Extensions\Nodes;

use Latte\CompileException;
use Latte\Compiler\Nodes\AreaNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;
use Latte\Compiler\TemplateParser;

/**
 * {antlers} {/antlers}
 */
final class AntlersNode extends StatementNode
{
    use Concerns\ExtractsToTemporaryView;

    protected string $viewFileExtension = 'antlers.html';

    /** @return \Generator<int, AreaNode|null> */
    public static function create(Tag $tag, TemplateParser $parser): \Generator
    {
        $node = $tag->node = new self;
        if (! $tag->parser->isEnd()) {
            throw new CompileException("Unexpected arguments in {$tag->getNotation()}", $tag->position);
        }
        if ($tag->isNAttribute()) {
            throw new CompileException('Attribute n:antlers is not supported.', $tag->position);
        }

        // Read inner content as raw text
        self::disableParserForTag($tag, $parser);
        [$node->content] = yield;
        self::restoreParserForTag($tag, $parser);

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
