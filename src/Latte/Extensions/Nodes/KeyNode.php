<?php

namespace Daun\StatamicLatte\Latte\Extensions\Nodes;

use Latte\CompileException;
use Latte\Compiler\Nodes\AreaNode;
use Latte\Compiler\Nodes\AuxiliaryNode;
use Latte\Compiler\Nodes\FragmentNode;
use Latte\Compiler\Nodes\Html\ElementNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;
use Latte\Compiler\TemplateParser;

/**
 * <div n:key>...</div>
 *
 * Captures the rendered outer HTML so attributes and dynamic content
 * contribute to its key.
 */
final class KeyNode extends StatementNode
{
    public AreaNode $content;

    public int $id;

    public bool $hasId;

    public ElementNode $htmlElement;

    /** @return \Generator<int, ?list<string>, array{AreaNode, ?Tag}, static> */
    public static function create(Tag $tag, TemplateParser $parser): \Generator
    {
        $node = $tag->node = new self;
        $node->id = $parser->generateId();

        if (! $tag->parser->isEnd()) {
            throw new CompileException("Unexpected arguments in {$tag->getNotation()}", $tag->position);
        }

        if (! $tag->isNAttribute()) {
            throw new CompileException('n:key is only usable as an attribute.', $tag->position);
        }

        if ($tag->prefix !== Tag::PrefixNone) {
            throw new CompileException('Only the n:key attribute is supported.', $tag->position);
        }

        if ($tag->htmlElement?->getAttribute('key') !== null) {
            throw new CompileException('It is not possible to combine key with n:key.', $tag->position);
        }

        $element = $tag->htmlElement;
        assert($element !== null);

        $node->htmlElement = $element;
        $node->hasId = $element->getAttribute('id') !== null;

        [$node->content] = yield;

        return $node;
    }

    public function print(PrintContext $context): string
    {
        $attributes = $this->htmlElement->attributes;
        $inner = $this->htmlElement->content;

        $context->beginEscape()->enterHtmlText($this->htmlElement);
        $renderedInner = $inner?->print($context) ?? '';
        $context->restoreEscape();

        $context->beginEscape()->enterHtmlTag($this->htmlElement->name);
        $renderedAttributes = $attributes->print($context);
        $context->restoreEscape();

        try {
            $this->htmlElement->attributes = new FragmentNode([
                new AuxiliaryNode(
                    fn () => $this->printAttributes($context),
                ),
            ]);
            if ($inner !== null) {
                $this->htmlElement->content = new AuxiliaryNode(
                    fn () => $context->format('echo $ʟ_keyContent[%dump];', $this->id),
                );
            }

            return $context->format(
                <<<'XX'
                    ob_start(fn() => '');
                    try {
                        %raw
                    } finally {
                        $ʟ_keyAttributes[%dump] = ob_get_clean();
                    }
                    ob_start(fn() => '');
                    try {
                        %raw
                    } finally {
                        $ʟ_keyContent[%dump] = ob_get_clean();
                    }
                    $ʟ_keyOuter[%dump] = %dump . $ʟ_keyAttributes[%dump] . %dump . $ʟ_keyContent[%dump] . %dump;
                    $ʟ_key[%dump] = hash('xxh128', $ʟ_keyOuter[%dump]);
                    %node
                    XX,
                $renderedAttributes,
                $this->id,
                $renderedInner,
                $this->id,
                $this->id,
                '<'.$this->htmlElement->name,
                $this->id,
                $this->htmlElement->selfClosing ? '/>' : '>',
                $this->id,
                $inner === null ? '' : "</{$this->htmlElement->name}>",
                $this->id,
                $this->id,
                $this->content,
            );
        } finally {
            $this->htmlElement->attributes = $attributes;
            $this->htmlElement->content = $inner;
        }
    }

    private function printAttributes(PrintContext $context): string
    {
        return $this->hasId
            ? $context->format(
                'echo $ʟ_keyAttributes[%dump], \' key="\', $ʟ_key[%dump], \'"\';',
                $this->id,
                $this->id,
            )
            : $context->format(
                'echo $ʟ_keyAttributes[%dump], \' id="key-\', $ʟ_key[%dump], \'" key="\', $ʟ_key[%dump], \'"\';',
                $this->id,
                $this->id,
                $this->id,
            );
    }

    public function &getIterator(): \Generator
    {
        yield $this->content;
    }
}
