<?php

namespace Daun\StatamicLatte\Extensions\Nodes;

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
                $ʟ_params = %node;
                if (\Daun\StatamicLatte\Support\Cache::enabled($ʟ_params)) {
                    $ʟ_store = \Daun\StatamicLatte\Support\Cache::store($ʟ_params);
                    $ʟ_key = \Daun\StatamicLatte\Support\Cache::key($ʟ_params, %dump);
                    $ʟ_expires = \Daun\StatamicLatte\Support\Cache::expires($ʟ_params);
                    if ($ʟ_output = $ʟ_store->get($ʟ_key)) %line {
                        echo $ʟ_output;
                    } else {
                        ob_start(fn() => '');
                        %node
                        $ʟ_output = ob_get_clean();
                        $ʟ_store->put($ʟ_key, $ʟ_output, $ʟ_expires);
                        echo $ʟ_output;
                    }
                } else {
                    %node
                }
                XX,
            $this->args,
            md5($this->content->print($context)),
            $this->position,
            $this->content,
            $this->content,
        );
    }

    public function &getIterator(): \Generator
    {
        yield $this->content;
    }
}
