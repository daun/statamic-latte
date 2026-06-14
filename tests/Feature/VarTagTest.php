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

describe('statamic tag assignment', function () {
    test('captures scalar tag output into the variable', function () {
        $this->latte('{var $count = (s:collection:count in: pages)}{$count} pages')
            ->assertSee('2 pages');
    });

    test('captures iterable tag output into the variable', function () {
        $this->latte(<<<'LATTE'
            {var $entries = (s:collection from: pages, order: title)}
            {foreach $entries as $entry}{$entry->title}{sep}, {/sep}{/foreach}
        LATTE)
            ->assertSee('Testable, Testable With Layout');
    });

    test('supports nested param keys', function () {
        $this->latte(<<<'LATTE'
            {var $entries = (s:collection from: pages, title:contains: Layout)}
            {foreach $entries as $entry}{$entry->title}{/foreach}
        LATTE)
            ->assertSee('Testable With Layout')
            ->assertDontSee('Testable,');
    });

    test('resolves variables inside params', function () {
        $this->latte(<<<'LATTE'
            {var $filter = 'Layout'}
            {var $entries = (s:collection from: pages, title:contains: $filter)}
            {foreach $entries as $entry}{$entry->title}{/foreach}
        LATTE)
            ->assertSee('Testable With Layout')
            ->assertDontSee('Testable,');
    });

    test('errors when combined with a filter (not yet supported)', function () {
        $this->latte('{var $count = (s:collection:count in: pages)|upper}{$count}');
    })->throws(\Latte\CompileException::class);
});
