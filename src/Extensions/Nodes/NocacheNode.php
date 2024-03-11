<?php

namespace Daun\StatamicLatte\Extensions\Nodes;

use Latte\Compiler\Nodes\AreaNode;
use Latte\Compiler\Nodes\Php\Expression\ArrayNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;
use Latte\Compiler\TemplateParser;

/**
 * {nocache} {/nocache}
 */
final class NocacheNode extends StatementNode
{
    use Concerns\ExtractsToTemporaryView;

    public ArrayNode $args;

    /** @return \Generator<int, AreaNode|null> */
    public static function create(Tag $tag, TemplateParser $parser): \Generator
    {
        $node = $tag->node = new self;
        $tag->parser->stream->tryConsume(',');
        $node->args = $tag->parser->parseArguments();

        // Read inner content as raw text
        self::disableParserForTag($tag, $parser);
        [$node->content] = yield;
        self::restoreParserForTag($tag, $parser);

        return $node;
    }

    public function print(PrintContext $context): string
    {
        return $context->format(
            'echo app("Statamic\StaticCaching\NoCache\BladeDirective")->handle(%dump, ["__layout_parent" => $this->getName()] + %node); %line;',
            $this->saveContentToView(),
            $this->args,
            $this->position,
        );
    }

    public function &getIterator(): \Generator
    {
        yield $this->content;
    }
}
