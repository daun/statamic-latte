<?php

namespace Daun\StatamicLatte\Latte\Extensions\Nodes;

use Daun\StatamicLatte\Latte\Support\Tags;
use Latte\CompileException;
use Latte\Compiler\Nodes\AreaNode;
use Latte\Compiler\Nodes\Php\Expression\ArrayNode;
use Latte\Compiler\Nodes\Php\Expression\VariableNode;
use Latte\Compiler\Nodes\Php\IdentifierNode;
use Latte\Compiler\Nodes\Php\Scalar\StringNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;
use Latte\Essential\Nodes\ForeachNode;

/**
 * {s:[tag]} ... {/s:[tag]}
 *
 * Renders a Statamic tag. Behaviour depends on what the tag returns and its
 * params; the fetched value is held internally in $ʟ_result:
 *
 *  - `as: name`   stores the raw result in a body-scoped $name variable and
 *                 renders the body once (you iterate it yourself).
 *  - iterable     loops the body over the result via Latte's own foreach,
 *                 exposing each item as $entry (with $iterator, {sep},
 *                 {first} and {last} support).
 *  - scalar       exposes the result to the body as $result and renders it
 *                 once; an empty or self-closing body falls back to echoing
 *                 the fetched output.
 *
 * Rendered body output is whitespace-squished, mirroring how templates are
 * normalised elsewhere, so loop separators produce clean output.
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
        [$args, $as] = $this->splitArguments();

        $fetch = $context->format(
            '$ʟ_result = \Daun\StatamicLatte\Latte\Support\Tags::fetch(%dump, %node); %line',
            $name,
            $args,
            $this->position,
        );

        if ($as !== null) {
            return $fetch."\n".$this->printAliased($context, $as);
        }

        return $fetch."\n".$context->format(
            <<<'XX'
                ob_start();
                $ʟ_iterable = is_iterable($ʟ_result);
                if ($ʟ_iterable) {
                    %raw
                } else {
                    $result = $ʟ_result;
                    %node
                }
                $ʟ_body = \Illuminate\Support\Str::squish(ob_get_clean());
                echo $ʟ_body === '' && ! $ʟ_iterable ? $ʟ_result : $ʟ_body;
                XX,
            $this->printForeach($context),
            $this->content,
        );
    }

    /**
     * Loop the body over the result using Latte's native foreach so that
     * $iterator, {sep}, {first} and {last} all work as expected.
     */
    protected function printForeach(PrintContext $context): string
    {
        $foreach = new ForeachNode;
        $foreach->expression = new VariableNode('ʟ_result');
        $foreach->value = new VariableNode('entry');
        $foreach->content = $this->content;

        return $foreach->print($context);
    }

    /**
     * Store the result in a body-scoped variable and render the body once.
     */
    protected function printAliased(PrintContext $context, string $as): string
    {
        if (! preg_match('#^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$#', $as)) {
            throw new CompileException("Invalid `as` variable name `{$as}`.");
        }

        $backup = '$ʟ_as_'.$context->generateId();
        $body = $this->content->print($context);

        return <<<XX
            ob_start();
            try {
                $backup = get_defined_vars();
                \$$as = \$ʟ_result;
                $body
            } finally {
                if (array_key_exists('$as', $backup)) { \$$as = {$backup}['$as']; } else { unset(\$$as); }
                unset($backup);
            }
            echo \Illuminate\Support\Str::squish(ob_get_clean());

            XX;
    }

    /**
     * Split the parsed arguments, pulling out the `as` alias (which is
     * consumed here rather than forwarded to the Statamic tag).
     *
     * @return array{ArrayNode, ?string}
     */
    protected function splitArguments(): array
    {
        $items = [];
        $as = null;

        foreach ($this->args->items as $item) {
            if ($as === null
                && $item->key instanceof IdentifierNode
                && $item->key->name === 'as'
                && $item->value instanceof StringNode
            ) {
                $as = $item->value->value;

                continue;
            }

            $items[] = $item;
        }

        return [new ArrayNode($items), $as];
    }

    public function &getIterator(): \Generator
    {
        yield $this->args;
        yield $this->content;
    }
}
