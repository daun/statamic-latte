<?php

namespace Daun\StatamicLatte\Latte\Extensions\Nodes;

use Daun\StatamicLatte\Latte\Support\TagArguments;
use Latte\CompileException;
use Latte\Compiler\Nodes\Php\Expression\ArrayNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;
use Latte\Essential\Nodes\VarNode as LatteVarNode;

/**
 * {var $name = (s:[tag] ...)}
 *
 * Overrides Latte's built-in {var} so the value may be a Statamic tag call
 * wrapped in parentheses, captured into the variable:
 *
 *     {var $count = (s:collection:count in: pages)}
 *
 * compiles to `$count = Tags::fetch('collection:count', ['in' => 'pages'])`.
 *
 * Any other assignment (`{var $count = 3}`, typed vars, multiple assignments,
 * etc.) is handed straight back to Latte's native {@see LatteVarNode}, so this
 * is a strict superset of the built-in tag.
 *
 * The parenthesised tag call must currently be the *entire* value; composing it
 * with filters or operators (e.g. `(s:...)|upper`) is a planned follow-up.
 */
final class VarNode extends StatementNode
{
    /** Matches `$var = ( s:<tag-call> )` as the whole value. */
    private const PATTERN = '#^\s*\$([A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)\s*=\s*\(\s*s:(.+)\)\s*$#s';

    /** Detects an attempt to use `(s:...)` that we cannot yet fully parse. */
    private const SNIFF = '#=\s*\(\s*s:#';

    public string $variable;

    public string $tagName;

    public ArrayNode $args;

    public static function create(Tag $tag): StatementNode
    {
        $text = $tag->parser->text;

        if (preg_match(self::PATTERN, $text, $matches)) {
            return self::createStatamic($tag, $matches[1], $matches[2]);
        }

        if (preg_match(self::SNIFF, $text)) {
            throw new CompileException(
                'A Statamic tag in {var} must currently be the entire value, '
                .'e.g. {var $x = (s:collection:count in: pages)}. '
                .'Combining it with filters or operators is not yet supported.'
            );
        }

        return LatteVarNode::create($tag);
    }

    private static function createStatamic(Tag $tag, string $variable, string $call): self
    {
        [$name, $args] = TagArguments::parse($call);

        $node = new self;
        $node->variable = $variable;
        $node->tagName = $name;
        $node->args = $args;

        // Drain the stream so Latte sees the arguments as consumed.
        while (! $tag->parser->isEnd()) {
            $tag->parser->stream->consume();
        }

        return $node;
    }

    public function print(PrintContext $context): string
    {
        return $context->format(
            '$%raw = \Daun\StatamicLatte\Latte\Support\Tags::fetch(%dump, %node); %line',
            $this->variable,
            $this->tagName,
            $this->args,
            $this->position,
        );
    }

    public function &getIterator(): \Generator
    {
        yield $this->args;
    }
}
