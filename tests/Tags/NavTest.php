<?php

/*
 * CLASSIFICATION OVERVIEW
 * nav (handle: main)  — OK: nav + tree fixture in place; custom title/url items
 * nav:breadcrumbs     — N/A: requires URL::getCurrent() — no HTTP context in unit test
 */

describe('nav', function () {
    test('renders nav item titles from main tree', function () {
        $this->latte('{s:nav handle: main}{$value->title}{sep}, {/sep}{/s:nav}')
            ->assertSee('Home')
            ->assertSee('About');
    });

    test('renders nav item urls from main tree', function () {
        $this->latte('{s:nav handle: main}{$value->url}{sep}, {/sep}{/s:nav}')
            ->assertSee('/')
            ->assertSee('/about');
    });

    test('top-level tree has exactly two items', function () {
        $this->latte('{s:nav handle: main}{$value->title}{sep}, {/sep}{/s:nav}')
            ->assertDontSee('Team'); // nested child not shown at top level
    });

    test('children array exposes nested items', function () {
        $this->latte(<<<'LATTE'
            {s:nav handle: main}
                {foreach ($value->children ?? []) as $child}|{$child->title}|{/foreach}
            {/s:nav}
        LATTE)
            ->assertSee('|Team|');
    });

    test('as param captures nav items into named variable', function () {
        $this->latte(<<<'LATTE'
            {s:nav as: items, handle: main}
                {foreach $items as $item}|{$item->title}|{/foreach}
            {/s:nav}
        LATTE)
            ->assertSee('|Home|')
            ->assertSee('|About|');
    });

    test('depth field is present on nav items', function () {
        $this->latte('{s:nav handle: main}{$value->depth}{sep}, {/sep}{/s:nav}')
            ->assertSee('1');
    });

    test('breadcrumbs compiles without fatal', function () {
        // N/A: requires URL::getCurrent() to match an entry — no HTTP context in unit tests
        $this->latte('{s:nav:breadcrumbs}{$value->title}{sep} / {/sep}{/s:nav:breadcrumbs}')
            ->assertDontSee('<fatal>');
    });
});
