<?php

namespace Daun\StatamicLatte\Extensions\Nodes\Traits;

use Latte\CompileException;
use Latte\Compiler\NodeHelpers;
use Latte\Compiler\Nodes\AreaNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;
use Latte\Compiler\TemplateParser;
use Latte\ContentType;

trait ExtractsToTemporaryView
{
    public AreaNode $content;

    public array $lexerDelimiters = [];

    public string $contentType = '';

    /** @return \Generator<int, ?array, array{AreaNode, ?Tag}, static> */
    public static function create(Tag $tag, TemplateParser $parser): \Generator
    {
        if (! $tag->parser->isEnd()) {
            throw new CompileException("Unexpected arguments in {$tag->getNotation()}", $tag->position);
        }

        $node = $tag->node = new static;

        // Read inner content as raw text
        static::disableParserForTag($tag, $parser);
        [$node->content] = yield;
        static::restoreParserForTag($tag, $parser);

        return $node;
    }

    protected static function disableParserForTag(Tag $tag, TemplateParser $parser): void
    {
        // Temporarily disable {} syntax
        $lexer = $parser->getLexer();
        $tag->node->lexerDelimiters = [$lexer->openDelimiter, $lexer->closeDelimiter];
        $lexer->setSyntax('off', $tag->isNAttribute() ? null : $tag->name);

        // Switch to text content type
        $tag->node->contentType = $parser->getContentType();
        $parser->setContentType(ContentType::Text);
    }

    protected static function restoreParserForTag(Tag $tag, TemplateParser $parser): void
    {
        // Restore previous syntax and content type
        $lexer = $parser->getLexer();
        [$lexer->openDelimiter, $lexer->closeDelimiter] = $tag->node->lexerDelimiters;
        $parser->setContentType($tag->node->contentType);
    }

    protected function saveContentToView(?string $extension = null): string
    {
        $content = NodeHelpers::toText($this->content);
        $extension = $extension ?? $this->viewFileExtension ?? 'latte';

        $hash = sha1($content);
        $dir = config('view.compiled');
        $view = "latte-tag-content-{$hash}";
        $path = "{$dir}/{$view}.{$extension}";

        if (! file_exists($path)) {
            file_put_contents($path, $content);
        }

        return "statamic-latte::{$view}";
    }

    public function print(PrintContext $context): string
    {
        return $context->format(
            'array_merge(%dump, get_defined_vars())->render() %line;',
            $this->saveContentToView(),
            $this->position
        );
    }

    public function &getIterator(): \Generator
    {
        yield $this->content;
    }
}
