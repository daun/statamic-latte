<?php

namespace Daun\StatamicLatte\Latte\Extensions\Nodes;

use Latte\CompileException;
use Latte\Compiler\Nodes\AreaNode;
use Latte\Compiler\Nodes\AuxiliaryNode;
use Latte\Compiler\Nodes\Html\ElementNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;
use Latte\Compiler\TemplateParser;

/**
 * {iftext} ... {/iftext}
 * <div n:iftext> ... </div>
 *
 * Like Latte's own n:ifcontent, but tests for *visible* content instead of any
 * output: tags are stripped before the emptiness check, so markup that renders
 * nothing (an empty <p>, a stray <span>) is treated as blank. Elements that
 * render on their own — images, form controls, embeds — still count.
 */
final class IfTextNode extends StatementNode
{
    public AreaNode $content;

    public ?AreaNode $else = null;

    /** The element to omit, when used as an outer n:attribute. */
    public ?ElementNode $htmlElement = null;

    public int $id;

    /** @return \Generator<int, ?list<string>, array{AreaNode, ?Tag}, static> */
    public static function create(Tag $tag, TemplateParser $parser): \Generator
    {
        $node = $tag->node = new self;
        $node->id = $parser->generateId();

        [$node->content, $nextTag] = yield ['else'];
        if ($nextTag?->name === 'else') {
            [$node->else] = yield;
        }

        // As `n:iftext` the whole element is omitted, so the test runs on the
        // element's content while the buffer covers the element itself.
        if ($tag->nAttribute && $tag->prefix === Tag::PrefixNone) {
            $node->htmlElement = $tag->htmlElement;
            if (! $node->htmlElement?->content) {
                throw new CompileException("Unnecessary n:iftext on empty element <{$node->htmlElement?->name}>", $tag->position);
            }
        }

        return $node;
    }

    public function print(PrintContext $context): string
    {
        return $this->htmlElement
            ? $this->printElement($context)
            : $this->printContent($context);
    }

    /** {iftext} / n:inner-iftext: buffer the content, echo it only if it holds text. */
    private function printContent(PrintContext $context): string
    {
        $else = $this->else?->print($context) ?? '';

        return <<<XX
            ob_start(fn() => '');
            try {
                {$this->content->print($context)}
            } finally {
                \$ʟ_ift[$this->id] = ob_get_clean();
            }
            if (\\Daun\\StatamicLatte\\Latte\\Support\\Html::hasText(\$ʟ_ift[$this->id])) {
                echo \$ʟ_ift[$this->id];
            } else {
                $else
            }


            XX;
    }

    /** n:iftext: buffer the element, drop it whole if its content held no text. */
    private function printElement(PrintContext $context): string
    {
        $saved = $this->htmlElement->content;
        assert($saved !== null);

        try {
            $else = $this->else?->print($context) ?? '';
            $this->htmlElement->content = new AuxiliaryNode(fn () => <<<XX
                ob_start();
                try {
                    {$saved->print($context)}
                } finally {
                    \$ʟ_ift[$this->id] = !\\Daun\\StatamicLatte\\Latte\\Support\\Html::hasText(ob_get_flush());
                }

                XX);

            return <<<XX
                ob_start(fn() => '');
                try {
                    {$this->content->print($context)}
                } finally {
                    if (\$ʟ_ift[$this->id] ?? null) {
                        ob_end_clean();
                        $else
                    } else {
                        echo ob_get_clean();
                    }
                }


                XX;
        } finally {
            $this->htmlElement->content = $saved;
        }
    }

    public function &getIterator(): \Generator
    {
        yield $this->content;
        if ($this->else) {
            yield $this->else;
        }
    }
}
