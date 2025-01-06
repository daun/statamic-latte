<?php

namespace Daun\StatamicLatte\Extensions\Nodes;

use Daun\StatamicLatte\Support\Tags;
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

    public bool $hasContent = false;

    public bool $selfClosing = false;

    protected static array $unsupportedTags = [
        'cache' => 'Use the built-in `{cache}` tag instead',
        'foreach' => 'Use the built-in `{foreach}` tag instead',
        'partial' => 'Use the built-in `{include}` or `embed` tag instead',
        'switch' => 'Use the built-in `{switch}` tag instead',
        'translate' => 'Use the built-in `|translate` filter instead',
        'yield' => 'Use the built-in `block` tag instead',
        'section' => 'Use the built-in `block` tag instead',
        'scope' => 'Not supported in Latte',
    ];

    /** @return \Generator<int, ?array, array{AreaNode, ?Tag}, static|AreaNode> */
    public static function create(Tag $tag): \Generator
    {
        $name = Tags::unprefix($tag->name);
        if ($msg = static::$unsupportedTags[$name] ?? false) {
            throw new CompileException("The `{$tag->name}` tag is not supported. {$msg}");
        }

        $node = $tag->node = new self;
        $node->name = $tag->name;
        $node->args = $tag->parser->parseArguments();

        [$node->content, $endTag] = yield;

        $node->hasContent = $endTag && $endTag->closing;
        $node->selfClosing = !$endTag || $endTag === $tag;

        return $node;
    }

    public function print(PrintContext $context): string
    {
        if ($this->selfClosing) {
            return $this->printStatic($context);
        } else {
            return $this->printDynamic($context);
        }
    }

    public function printStatic(PrintContext $context): string
    {
        $name = Tags::unprefix($this->name);

        return $context->format(
            'echo \Daun\StatamicLatte\Support\Tags::fetch(%dump, %node); %line',
            $name,
            $this->args,
            $this->position,
        );
    }

    public function printDynamic(PrintContext $context): string
    {
        $name = Tags::unprefix($this->name);

        return $context->format(
            <<<'XX'
                $ʟ_result = \Daun\StatamicLatte\Support\Tags::fetch(%dump, %node); %line
                ray('in template', %dump, %node, $ʟ_result);
                $result = $ʟ_result;
                %node
                XX,
            $name,
            $this->args,
            $this->position,
            $this->content,
            $this->args,
            $this->position,
        );
    }

    public function &getIterator(): \Generator
    {
        yield $this->content;
    }
}
