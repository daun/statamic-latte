<?php

describe('scalar tags', function () {
    test('passes value variable into tag pair context', function () {
        $this->latte('A link to {s:link to: "snacks"}{$value}{/s:link}')->assertSee('A link to /snacks');
    });

    test('renders empty tag pair using value variable', function () {
        $this->latte('Go to {s:link to: "snacks"}{/s:link} link')->assertSee('Go to /snacks link');
    });

    test('renders self-closing s:link tag using value variable', function () {
        $this->latte('Another {s:link to: "snacks"/} link')->assertSee('Another /snacks link');
    });

    // Not supported in Latte 3+ — see https://github.com/nette/latte/issues/382
    // test('renders s:link single tag', function () {
    //     $this->latte('Another {s:link to: "snacks"} link')->assertSee('Another /snacks link');
    // });
});

describe('iterable tags', function () {
    test('renders iterable statamic tags using foreach loop', function () {
        $this->latte(<<<'LATTE'
            {s:collection from: pages, order: title}
                {$value->title}{sep}, {/sep}
            {/s:collection}
        LATTE)
            ->assertSee('Testable, Testable With Layout');
    });

    test('saves result into local variable using `as` param', function () {
        $this->latte(<<<'LATTE'
            {s:collection as: entries, from: pages, order: title}
                {foreach $entries as $entry}
                    {$entry->title}{sep}, {/sep}
                {/foreach}
            {/s:collection}
        LATTE)
            ->assertSee('Testable, Testable With Layout');
    });
});

describe('paginated tags', function () {
    test('iterates a paginated result as a laravel paginator', function () {
        $this->latte(<<<'LATTE'
            {s:collection from: pages, order: title, paginate: 1}
                |{$value->title}|
            {/s:collection}
        LATTE)
            ->assertSee('|Testable|')
            ->assertDontSee('Testable With Layout');
    });

    test('exposes the paginator api via the `as` param', function () {
        $this->latte(<<<'LATTE'
            {s:collection as: paginator, from: pages, order: title, paginate: 1}
                total:{$paginator->total()} pages:{$paginator->lastPage()} page:{$paginator->currentPage()} count:{$paginator->count()}
            {/s:collection}
        LATTE)
            ->assertSee('total:2 pages:2 page:1 count:1');
    });

    test('loops the paginator and prints page meta via the `as` param', function () {
        $this->latte(<<<'LATTE'
            {s:collection as: entries, from: pages, order: title, paginate: 1}
                {foreach $entries as $entry}|{$entry->title}|{/foreach}
                Showing page {$entries->currentPage()} of {$entries->lastPage()}, {$entries->total()} total
            {/s:collection}
        LATTE)
            ->assertSee('|Testable|')
            ->assertDontSee('Testable With Layout')
            ->assertSee('Showing page 1 of 2, 2 total');
    });

    test('captures a paginator from a tag subexpression into a variable', function () {
        $this->latte(<<<'LATTE'
            {var $entries = (s:collection from: pages, order: title, paginate: 1)}
            {foreach $entries as $entry}|{$entry->title}|{/foreach}
            Showing page {$entries->currentPage()} of {$entries->lastPage()}, {$entries->total()} total
        LATTE)
            ->assertSee('|Testable|')
            ->assertDontSee('Testable With Layout')
            ->assertSee('Showing page 1 of 2, 2 total');
    });
});

describe('params', function () {
    test('accepts nested params', function () {
        $this->latte(<<<'LATTE'
            {s:collection from: pages, status:is => draft}
                {$value->title}{sep}, {/sep}
            {/s:collection}
        LATTE)
            ->assertDontSee('Testable,')
            ->assertSee('Testable Draft');

        $this->latte(<<<'LATTE'
            {s:collection from: pages, title:contains: Layout}
                {$value->title}{sep}, {/sep}
            {/s:collection}
        LATTE)
            ->assertDontSee('Testable,')
            ->assertSee('Testable With Layout');

        $this->latte(<<<'LATTE'
            {s:collection from: pages, title:contains:"Layout"}
                {$value->title}{sep}, {/sep}
            {/s:collection}
        LATTE)
            ->assertDontSee('Testable,')
            ->assertSee('Testable With Layout');

        $this->latte(<<<'LATTE'
            {s:collection from: pages, title:contains:Layout}
                {$value->title}{sep}, {/sep}
            {/s:collection}
        LATTE)
            ->assertDontSee('Testable,')
            ->assertSee('Testable With Layout');

        $this->latte(<<<'LATTE'
            {var $titleFilter = 'Layout'}
            {s:collection from: pages, title:contains: $titleFilter}
                {$value->title}{sep}, {/sep}
            {/s:collection}
        LATTE)
            ->assertDontSee('Testable,')
            ->assertSee('Testable With Layout');

        $this->latte(<<<'LATTE'
            {var $titleFilter = 'Layout'}
            {s:collection from: pages, title:contains:$titleFilter}
                {$value->title}{sep}, {/sep}
            {/s:collection}
        LATTE)
            ->assertDontSee('Testable,')
            ->assertSee('Testable With Layout');

        $this->latte(<<<'LATTE'
            {var $titleFilter1 = 'With '}
            {var $titleFilter2 = 'Layout'}
            {s:collection from: pages, title:contains:$titleFilter1.$titleFilter2}
                {$value->title}{sep}, {/sep}
            {/s:collection}
        LATTE)
            ->assertDontSee('Testable,')
            ->assertSee('Testable With Layout');
    });
});

describe('tag methods', function () {
    test('accepts tag methods', function () {
        $this->latte('{s:collection:count in: pages /} pages')
            ->assertSee('2 pages');

        $this->latte('{s:collection:count in: pages}{$value}{/s:collection:count} pages')
            ->assertSee('2 pages');

        $this->latte('{s:collection:count in: pages, as: count}{$count}{/s:collection:count} pages')
            ->assertSee('2 pages');
    });

    test('accepts wildcard tag methods', function () {
        $this->latte('{s:collection:pages order: title}{$value->title}{sep}, {/sep}{/s:collection:pages}')
            ->assertSee('Testable, Testable With Layout');
    });
});
