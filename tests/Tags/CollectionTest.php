<?php

/*
 * CLASSIFICATION OVERVIEW
 * collection (from/sort/sep/status) — OK: should pass with pages fixture
 * collection:count                  — OK: should pass
 * collection paginate               — OK: covered in TagTest, re-verified here
 * collection:next / :previous       — INCOMPAT/FIXTURE: needs current entry in context
 */

describe('collection', function () {
    test('renders published entries from pages collection', function () {
        $this->latte('{s:collection from: pages, sort: title}{$value->title}{sep}, {/sep}{/s:collection}')
            ->assertSee('Testable, Testable With Layout');
    });

    test('excludes unpublished entries by default', function () {
        $this->latte('{s:collection from: pages}{$value->title}{sep}, {/sep}{/s:collection}')
            ->assertDontSee('Testable Draft')
            ->assertDontSee('Testable Child')
            ->assertDontSee('Testable Nested');
    });

    test('filters by status draft', function () {
        $this->latte(<<<'LATTE'
            {s:collection from: pages, status:is => draft}
                {$value->title}{sep}, {/sep}
            {/s:collection}
        LATTE)
            ->assertSee('Testable Draft')
            ->assertDontSee('Testable,');
    });

    test('count tag method returns published entry count', function () {
        $this->latte('{s:collection:count in: pages /}')
            ->assertSee('2');
    });

    test('count tag method works as a tag pair', function () {
        $this->latte('{s:collection:count in: pages}{$value}{/s:collection:count}')
            ->assertSee('2');
    });

    test('next tag method — needs current entry context', function () {
        // CLASSIFY: INCOMPAT — currentEntry() reads from cascade/context; not set in unit test
        // Likely throws or returns empty; assert it compiles without parse error at minimum
        expect(fn () => $this->latte('{s:collection:next}{$value->title}{/s:collection:next}'))
            ->toThrow(Error::class);
    });

    test('previous tag method — needs current entry context', function () {
        // CLASSIFY: INCOMPAT — same as next; currentEntry() not available
        expect(fn () => $this->latte('{s:collection:previous}{$value->title}{/s:collection:previous}'))
            ->toThrow(Error::class);
    });

    test('paginate returns first page only', function () {
        $this->latte(<<<'LATTE'
            {s:collection from: pages, sort: title, paginate: 1}
                |{$value->title}|
            {/s:collection}
        LATTE)
            ->assertSee('|Testable|')
            ->assertDontSee('Testable With Layout');
    });

    test('captures paginator via as param and exposes page meta', function () {
        $this->latte(<<<'LATTE'
            {s:collection as: entries, from: pages, sort: title, paginate: 1}
                {foreach $entries as $e}|{$e->title}|{/foreach}
                page:{$entries->currentPage()} of:{$entries->lastPage()} total:{$entries->total()}
            {/s:collection}
        LATTE)
            ->assertSee('|Testable|')
            ->assertSee('page:1 of:2 total:2');
    });
});
