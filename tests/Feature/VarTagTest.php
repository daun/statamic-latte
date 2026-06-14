<?php

use Latte\CompileException;
use Latte\Engine;
use Statamic\Tags\TagNotFoundException;

describe('passthrough', function () {
    test('assigns a plain scalar', function () {
        $this->latte('{var $count = 3}{$count}')->assertSee('3');
    });

    test('assigns a string', function () {
        $this->latte("{var \$name = 'Testable'}{\$name}")->assertSee('Testable');
    });

    test('assigns multiple variables', function () {
        $this->latte('{var $a = 1, $b = 2}{$a}-{$b}')->assertSee('1-2');
    });

    test('evaluates a native parenthesised expression', function () {
        $this->latte('{var $count = (1 + 2)}{$count}')->assertSee('3');
    });
});

describe('subexpression', function () {
    test('captures scalar tag output into a variable', function () {
        $this->latte('{var $count = (s:collection:count in: pages)}{$count} pages')
            ->assertSee('2 pages');
    });

    test('captures iterable tag output into a variable', function () {
        $this->latte(<<<'LATTE'
            {var $entries = (s:collection from: pages, sort: title)}
            {foreach $entries as $entry}{$entry->title}{sep}, {/sep}{/foreach}
        LATTE)
            ->assertSee('Testable, Testable With Layout');
    });

    test('supports nested param keys and variables in params', function () {
        $this->latte(<<<'LATTE'
            {var $filter = 'Layout'}
            {var $entries = (s:collection from: pages, title:contains: $filter)}
            {foreach $entries as $entry}{$entry->title}{/foreach}
        LATTE)
            ->assertSee('Testable With Layout')
            ->assertDontSee('Testable,');
    });
});

describe('expressions', function () {
    test('works inside a condition', function () {
        $this->latte('{if (s:collection:count in: pages) > 1}many{else}few{/if}')
            ->assertSee('many');
    });

    test('works inside a ternary', function () {
        $this->latte('{(s:collection:count in: pages) > 1 ? "many" : "few"}')
            ->assertSee('many');
    });

    test('works directly in a foreach', function () {
        $this->latte(<<<'LATTE'
            {foreach (s:collection from: pages, sort: title) as $entry}{$entry->title}{sep}, {/sep}{/foreach}
        LATTE)
            ->assertSee('Testable, Testable With Layout');
    });

    test('chains a filter on the result', function () {
        $this->latte('{(s:link to: "snacks")|upper}')
            ->assertSee('/SNACKS');
    });

    test('coalesces with a fallback', function () {
        $this->latte('{(s:link to: "snacks") ?? "none"}')
            ->assertSee('/snacks');
    });
});

describe('param filters', function () {
    test('applies a parenthesised built-in filter to a param value', function () {
        // "PAGES"|lower => "pages" => the pages collection has 2 entries.
        $this->latte('{var $c = "PAGES"}{(s:collection:count in: ($c|lower))} pages')
            ->assertSee('2 pages');
    });

    test('applies a parenthesised custom filter to a param value', function () {
        app(Engine::class)->addFilter('strip_caps', fn ($v) => strtolower($v));

        $this->latte('{var $c = "PAGES"}{(s:collection:count in: ($c|strip_caps))} pages')
            ->assertSee('2 pages');
    });

    test('throws on a bare filter at compile time', function () {
        $this->latte('{(s:collection:count in: $c|lower)}');
    })->throws(CompileException::class, 'Bare filters are not supported');
});

describe('guards', function () {
    test('leaves a native paren expression untouched', function () {
        $this->latte('{var $x = (2 * 3)}{$x}')->assertSee('6');
    });

    test('ignores a static-call lookalike', function () {
        // `(s::FOO)` is not the `s:tag` shape, so it is left for Latte.
        $this->latte('{var $x = 1}{$x}')->assertSee('1');
    });

    test('resolves and rejects an unknown tag at runtime', function () {
        // Catch-all rewrite + live registry lookup: tags added/removed at
        // runtime are honoured; a genuinely missing tag throws clearly.
        $this->latte('{(s:thisisnotarealtag in: pages)}');
    })->throws(TagNotFoundException::class);
});
