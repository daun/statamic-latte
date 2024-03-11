<?php

namespace Daun\StatamicLatte\Extensions\Nodes\Concerns;

use Daun\StatamicLatte\ServiceProvider;
use Latte\Compiler\NodeHelpers;
use Latte\Compiler\Nodes\AreaNode;
use Latte\Compiler\Tag;
use Latte\Compiler\TemplateParser;
use Latte\ContentType;
use WeakMap;

trait ExtractsToTemporaryView
{
    public AreaNode $content;

    public static ?WeakMap $lexerDelimiters;

    public static ?WeakMap $contentTypes;

    protected static function disableParserForTag(Tag $tag, TemplateParser $parser): void
    {
        static::$lexerDelimiters = static::$lexerDelimiters ?? new WeakMap();
        static::$contentTypes = static::$contentTypes ?? new WeakMap();

        // Temporarily disable {} syntax
        $lexer = $parser->getLexer();
        static::$lexerDelimiters[$tag] = [$lexer->openDelimiter, $lexer->closeDelimiter];
        $lexer->setSyntax('off', $tag->isNAttribute() ? null : $tag->name);

        // Switch to text content type
        static::$contentTypes[$tag] = $parser->getContentType();
        $parser->setContentType(ContentType::Text);
    }

    protected static function restoreParserForTag(Tag $tag, TemplateParser $parser): void
    {
        // Restore previous syntax and content type
        $lexer = $parser->getLexer();
        [$lexer->openDelimiter, $lexer->closeDelimiter] = static::$lexerDelimiters[$tag];
        $parser->setContentType(static::$contentTypes[$tag]);
    }

    protected function saveContentToView(?string $extension = null): string
    {
        $content = NodeHelpers::toText($this->content);
        $extension = $extension ?? $this->viewFileExtension ?? 'latte';

        $ns = ServiceProvider::$temporaryViewNamespace;
        $hash = sha1($content);
        $dir = config('view.compiled');
        $view = "latte-tag-content-{$hash}";
        $path = "{$dir}/{$view}.{$extension}";

        if (! file_exists($path)) {
            file_put_contents($path, $content);
        }

        return "{$ns}::{$view}";
    }
}
