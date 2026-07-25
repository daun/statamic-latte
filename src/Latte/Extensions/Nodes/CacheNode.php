<?php

namespace Daun\StatamicLatte\Latte\Extensions\Nodes;

use Latte\Compiler\Nodes\AreaNode;
use Latte\Compiler\Nodes\Php\Expression\ArrayNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;

/**
 * {cache} ... {/cache}
 * <div n:cache></div>
 */
final class CacheNode extends StatementNode
{
    public ArrayNode $args;

    public AreaNode $content;

    /** @return \Generator<int, AreaNode|null> */
    public static function create(Tag $tag): \Generator
    {
        $node = $tag->node = new self;
        $node->args = $tag->parser->parseArguments();
        [$node->content] = yield;

        return $node;
    }

    public function print(PrintContext $context): string
    {
        return $context->format(
            <<<'XX'
                if (\Daun\StatamicLatte\Latte\Support\Cache::open(%node, %dump)) %line {
                    try {
                        %node
                        \Daun\StatamicLatte\Latte\Support\Cache::close();
                    } catch (\Throwable $ʟ_e) {
                        \Daun\StatamicLatte\Latte\Support\Cache::abort();
                        throw $ʟ_e;
                    }
                }
                XX,
            $this->args,
            md5($this->content->print($context)),
            $this->position,
            $this->content,
        );
    }

    public function &getIterator(): \Generator
    {
        yield $this->content;
    }
}
