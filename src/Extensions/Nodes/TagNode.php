<?php

namespace Daun\StatamicLatte\Extensions\Nodes;

use Latte\CompileException;
use Latte\Compiler\Node;
use Latte\Compiler\Nodes;
use Latte\Compiler\Nodes\AreaNode;
use Latte\Compiler\Nodes\Php\Expression\ArrayNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\NodeTraverser;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;

/**
 * {s:[tag]} {/s:[tag]}
 */
final class TagNode extends StatementNode
{
    public string $name;

    public ArrayNode $args;

    public AreaNode $content;

    public bool $selfClosing = false;

    /** @return \Generator<int, AreaNode|null> */
    public static function create(Tag $tag): \Generator
    {
        $node = $tag->node = new self;
        $node->name = $tag->name;
        $node->args = $tag->parser->parseArguments();
        ray('tag', $tag);
        [$node->content] = yield;
        ray('content', $node->content);
        $node->selfClosing = (
            !$node->content ||
            $node->content instanceof Nodes\NopNode ||
            ($node->content instanceof Nodes\TextNode && trim($node->content->content) === '') ||
            ($node->content instanceof Nodes\FragmentNode && count($node->content->children) === 0)
        );

        return $node;
    }

    public function print(PrintContext $context): string
    {
        return $context->format(
            <<<'XX'
                $ʟ_name = \Daun\StatamicLatte\Support\Tags::unprefix(%dump);
                $ʟ_params = %node;
                $ʟ_print = %dump;
                $ʟ_result = \Daun\StatamicLatte\Support\Tags::fetch($ʟ_name, $ʟ_params); %line
                ray('in template', $ʟ_name, $ʟ_params, $ʟ_result);
                $result = $ʟ_result;

                %node
                XX,
            $this->name,
            $this->args,
            $this->selfClosing,
            $this->position,
            $this->content,
        );
    }

    public function &getIterator(): \Generator
    {
        yield $this->content;
    }

    public static function processPass(Node $node): void
    {
        ray($node);
    }
}
