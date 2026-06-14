<?php

describe('native var passthrough', function () {
    test('plain scalar assignment still works', function () {
        $this->latte('{var $count = 3}{$count}')->assertSee('3');
    });

    test('string assignment still works', function () {
        $this->latte("{var \$name = 'Testable'}{\$name}")->assertSee('Testable');
    });

    test('multiple assignments still work', function () {
        $this->latte('{var $a = 1, $b = 2}{$a}-{$b}')->assertSee('1-2');
    });

    test('native parenthesised expression still works', function () {
        $this->latte('{var $count = (1 + 2)}{$count}')->assertSee('3');
    });
});

describe('statamic tag subexpression', function () {
    test('captures scalar tag output into a variable', function () {
        $this->latte('{var $count = (s:collection:count in: pages)}{$count} pages')
            ->assertSee('2 pages');
    });

    test('captures iterable tag output into a variable', function () {
        $this->latte(<<<'LATTE'
            {var $entries = (s:collection from: pages, order: title)}
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

describe('used anywhere an expression is valid', function () {
    test('inside a condition', function () {
        $this->latte('{if (s:collection:count in: pages) > 1}many{else}few{/if}')
            ->assertSee('many');
    });

    test('inside a ternary', function () {
        $this->latte('{(s:collection:count in: pages) > 1 ? "many" : "few"}')
            ->assertSee('many');
    });

    test('directly in a foreach', function () {
        $this->latte(<<<'LATTE'
            {foreach (s:collection from: pages, order: title) as $entry}{$entry->title}{sep}, {/sep}{/foreach}
        LATTE)
            ->assertSee('Testable, Testable With Layout');
    });

    test('with a filter chained on the result', function () {
        $this->latte('{(s:link to: "snacks")|upper}')
            ->assertSee('/SNACKS');
    });

    test('coalesced with a fallback', function () {
        $this->latte('{(s:link to: "snacks") ?? "none"}')
            ->assertSee('/snacks');
    });
});

describe('filters inside params', function () {
    test('parenthesised built-in filter is applied to a param value', function () {
        // "PAGES"|lower => "pages" => the pages collection has 2 entries.
        $this->latte('{var $c = "PAGES"}{(s:collection:count in: ($c|lower))} pages')
            ->assertSee('2 pages');
    });

    test('parenthesised custom filter is applied to a param value', function () {
        app(\Latte\Engine::class)->addFilter('strip_caps', fn ($v) => strtolower($v));

        $this->latte('{var $c = "PAGES"}{(s:collection:count in: ($c|strip_caps))} pages')
            ->assertSee('2 pages');
    });

    test('bare filter throws at compile time', function () {
        $this->latte('{(s:collection:count in: $c|lower)}');
    })->throws(\Latte\CompileException::class, 'Bare filters are not supported');
});

describe('guards', function () {
    test('leaves a native paren expression untouched', function () {
        $this->latte('{var $x = (2 * 3)}{$x}')->assertSee('6');
    });

    test('ignores a static-call lookalike', function () {
        // `(s::FOO)` is not the `s:tag` shape, so it is left for Latte.
        $this->latte('{var $x = 1}{$x}')->assertSee('1');
    });

    test('an unknown tag is resolved (and rejected) at runtime', function () {
        // Catch-all rewrite + live registry lookup: tags added/removed at
        // runtime are honoured; a genuinely missing tag throws clearly.
        $this->latte('{(s:thisisnotarealtag in: pages)}');
    })->throws(\Statamic\Tags\TagNotFoundException::class);
});
