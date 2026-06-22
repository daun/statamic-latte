<?php

namespace Daun\StatamicLatte\Latte\Extensions\Nodes;

use Daun\StatamicLatte\Latte\Support\TagArguments;
use Daun\StatamicLatte\Latte\Support\TagMethodSyntax;
use Daun\StatamicLatte\Latte\Support\Tags;
use Latte\CompileException;
use Latte\Compiler\Nodes\AreaNode;
use Latte\Compiler\Nodes\Php\Expression\ArrayNode;
use Latte\Compiler\Nodes\Php\Expression\VariableNode;
use Latte\Compiler\Nodes\Php\ExpressionNode;
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
 *                 exposing each item as $value (with $iterator, {sep},
 *                 {first} and {last} support).
 *  - scalar       exposes the result to the body as $value and renders it
 *                 once; an empty or self-closing body falls back to echoing
 *                 the fetched output.
 *
 *
 * Parameters accept Statamic-style nested keys (e.g. `title:contains: foo`),
 * which Latte's own argument grammar would otherwise reject.
 */
final class TagNode extends StatementNode
{
    /** Internal argument inserted by the loader to preserve Statamic tag method names. */
    private const ORIGINAL_TAG_ARGUMENT = TagMethodSyntax::TAG_ARGUMENT;

    public string $name;

    public ArrayNode $args;

    public AreaNode $content;

    public bool $selfClosing = false;

    protected static array $unsupportedTags = [
        'cache' => 'Use the built-in `{cache}` tag instead',
        'foreach' => 'Use the built-in `{foreach}` tag instead',
        'partial' => 'Use the built-in `{include}` or `{embed}` tag instead',
        'switch' => 'Use the built-in `{switch}` tag instead',
        'translate' => 'Use the built-in `{_}` tag or `|translate` filter instead',
        'trans' => 'Use the built-in `{_}` tag or `|translate` filter instead',
        'trans_choice' => 'Use the built-in `{_}` tag or `|translate` filter instead',
        'yield' => 'Use the built-in `{yield}` tag instead',
        'section' => 'Use the built-in `{section}` tag instead',
        'scope' => 'Not supported in Latte',
        'loop' => 'Use the built-in `{for}` or `{foreach}` tag instead',
        'increment' => 'Use variable assigment inside a loop instead',
        'dump' => 'Use the built-in `{dump}` tag instead',
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
        $node->args = self::parseArguments($tag);

        [$node->content, $endTag] = yield;

        $node->selfClosing = ! $endTag || $endTag === $tag;

        return $node;
    }

    /**
     * Parse the tag arguments, allowing Statamic-style nested keys such as
     * `title:contains: foo`. Colons inside a key are masked with a placeholder
     * so Latte's argument grammar accepts them, then restored afterwards.
     */
    protected static function parseArguments(Tag $tag): ArrayNode
    {
        $args = TagArguments::parseParams($tag->parser->text);

        // Drain the original stream so Latte sees the arguments as consumed.
        while (! $tag->parser->isEnd()) {
            $tag->parser->stream->consume();
        }

        return $args;
    }

    public function print(PrintContext $context): string
    {
        [$args, $as, $originalTag, $content] = $this->splitArguments();
        $name = $originalTag ?? Tags::unprefix($this->name);

        // A `content:` argument supplies the tag-pair body as an already-rendered
        // string (e.g. `{s:widont content: $text/}`), routed through
        // fetchWithContent() so content-consuming tags receive it as `$this->content`.
        $fetch = $content !== null
            ? $context->format(
                '$ʟ_result = \Daun\StatamicLatte\Latte\Support\Tags::fetchWithContent(%dump, (string) (%node), %node); %line',
                $name,
                $content,
                $args,
                $this->position,
            )
            : $context->format(
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
                $ʟ_iterable = is_iterable($ʟ_result) && ! $ʟ_result instanceof \Daun\StatamicLatte\Data\Content;
                if ($ʟ_iterable) {
                    %raw
                } elseif ($ʟ_result !== null && $ʟ_result !== '' && $ʟ_result !== false) {
                    $value = $ʟ_result;
                    %node
                }
                $ʟ_body = ob_get_clean();
                echo $ʟ_body !== '' || $ʟ_iterable
                    ? $ʟ_body
                    : \Daun\StatamicLatte\Latte\Support\Tags::stringifyResult($ʟ_result);
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
        $foreach->value = new VariableNode('value');
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
            echo ob_get_clean();

            XX;
    }

    /**
     * Split the parsed arguments, pulling out the `as` alias (which is
     * consumed here rather than forwarded to the Statamic tag).
     *
     * @return array{ArrayNode, ?string, ?string, ?ExpressionNode}
     */
    protected function splitArguments(): array
    {
        $items = [];
        $as = null;
        $originalTag = null;
        $content = null;

        foreach ($this->args->items as $item) {
            if ($item->key instanceof IdentifierNode
                && $item->key->name === self::ORIGINAL_TAG_ARGUMENT
            ) {
                if (! $item->value instanceof StringNode) {
                    throw new CompileException('Invalid internal Statamic tag argument.');
                }

                $originalTag = $item->value->value;

                continue;
            }

            if ($as === null
                && $item->key instanceof IdentifierNode
                && $item->key->name === 'as'
                && $item->value instanceof StringNode
            ) {
                $as = $item->value->value;

                continue;
            }

            if ($content === null
                && $item->key instanceof IdentifierNode
                && $item->key->name === 'content'
            ) {
                $content = $item->value;

                continue;
            }

            $items[] = $item;
        }

        return [new ArrayNode($items), $as, $originalTag, $content];
    }

    public function &getIterator(): \Generator
    {
        yield $this->args;
        yield $this->content;
    }
}
