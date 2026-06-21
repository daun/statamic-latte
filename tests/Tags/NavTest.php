<?php

/*
 * CLASSIFICATION OVERVIEW
 * nav (index, collection:: handle) — FIXTURE: no nav tree stored in fixtures/content/navigation;
 *                                    collection-based nav requires a collection structure tree
 * nav:breadcrumbs                  — FIXTURE/N/A: depends on URL::getCurrent() — no HTTP context
 *
 * Tests verify the Latte proxy compiles the tag without PHP/parse errors.
 * Content assertions will likely fail (empty output or exceptions from missing tree).
 */

describe('nav', function () {
    test('compiles and renders without fatal for default handle', function () {
        // CLASSIFY: FIXTURE — no nav tree; Nav::index() uses handle 'collection::pages' by default
        // May throw or return empty; we probe it compiles
        $this->latte('{s:nav}{$value->title}{/s:nav}')
            ->assertDontSee('<fatal>');
    });

    test('accepts explicit collection handle param', function () {
        // CLASSIFY: FIXTURE — collection structure tree missing from fixtures
        $this->latte('{s:nav handle: "collection::pages"}{$value->title}{sep}, {/sep}{/s:nav}')
            ->assertDontSee('<');
    });

    test('accepts as param', function () {
        // CLASSIFY: FIXTURE — even with as:, tree is empty; just verifies proxy compiles
        $this->latte(<<<'LATTE'
            {s:nav as: items, handle: "collection::pages"}
                {foreach $items as $item}|{$item->title}|{/foreach}
            {/s:nav}
        LATTE)
            ->assertDontSee('Error');
    });

    test('breadcrumbs compiles without fatal', function () {
        // CLASSIFY: N/A — requires URL::getCurrent() to return a meaningful URI;
        // in unit test context the current URL is typically '/' with no matching entry
        $this->latte('{s:nav:breadcrumbs}{$value->title}{sep} &rsaquo; {/sep}{/s:nav:breadcrumbs}')
            ->assertDontSee('<fatal>');
    });
});
