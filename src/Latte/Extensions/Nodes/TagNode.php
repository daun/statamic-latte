<?php

namespace Daun\StatamicLatte\Latte\Extensions\Nodes;

use Daun\StatamicLatte\Latte\Support\Tags;
use Latte\CompileException;
use Latte\Compiler\Nodes\AreaNode;
use Latte\Compiler\Nodes\Php\Expression\ArrayNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;

/**
 * {s:[tag]} ... {/s:[tag]}
 *
 * Renders a Statamic tag. The fetched output is exposed to the tag body
 * as $result. A body may use $result to render custom markup; an empty
 * or self-closing tag falls back to echoing the fetched output directly.
 */
final class TagNode extends StatementNode
{
    public string $name;

    public ArrayNode $args;

    public AreaNode $content;

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

    /** @return \Generator<int, ?array, array{AreaNode, ?Tag}, static> */
    public static function create(Tag $tag): \Generator
    {
        $name = Tags::unprefix($tag->name);
        if ($msg = self::$unsupportedTags[$name] ?? false) {
            throw new CompileException("The `{$tag->name}` tag is not supported. {$msg}");
        }

        $node = $tag->node = new self;
        $node->name = $tag->name;
        $node->args = $tag->parser->parseArguments();

        [$node->content, $endTag] = yield;

        $node->selfClosing = ! $endTag || $endTag === $tag;

        return $node;
    }

    public function print(PrintContext $context): string
    {
        $name = Tags::unprefix($this->name);

        return $context->format(
            <<<'XX'
                $result = \Daun\StatamicLatte\Latte\Support\Tags::fetch(%dump, %node); %line
                ob_start();
                %node
                $ʟ_body = ob_get_clean();
                echo $ʟ_body === '' ? $result : $ʟ_body;
                XX,
            $name,
            $this->args,
            $this->position,
            $this->content,
        );
    }

    public function &getIterator(): \Generator
    {
        yield $this->args;
        yield $this->content;
    }
}
