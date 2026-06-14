<?php

describe('helpers', function () {
    test('provides the statamic() helper function', function () {
        $this->latte('<a href="{s(link, to: "fanny-packs")}"></a>')
            ->assertSee('<a href="/fanny-packs"></a>', false);

        $this->latte(<<<'LATTE'
            {foreach s("collection", ["from" => "pages", "title:contains" => "Layout"]) as $entry}
                {$entry->title}{sep}, {/sep}
            {/foreach}
        LATTE)
            ->assertSee('Testable With Layout')
            ->assertDontSee('Testable,');
    });

    test('captures a paginator into a variable for foreach and page meta', function () {
        $this->latte(<<<'LATTE'
            {var $entries = s("collection", ["from" => "pages", "sort" => "title", "paginate" => 1])}
            {foreach $entries as $entry}
                |{$entry->title}|
            {/foreach}
            Showing page {$entries->currentPage()} of {$entries->lastPage()}, {$entries->total()} total
        LATTE)
            ->assertSee('|Testable|')
            ->assertDontSee('Testable With Layout')
            ->assertSee('Showing page 1 of 2, 2 total');
    });

    test('provides the s() helper function as an alias', function () {
        $this->latte('<a href="{s(link, to: "fanny-packs")}"></a>')
            ->assertSee('<a href="/fanny-packs"></a>', false);

        $this->latte(<<<'LATTE'
            {foreach s("collection", ["from" => "pages", "title:contains" => "Layout"]) as $entry}
                {$entry->title}{sep}, {/sep}
            {/foreach}
        LATTE)
            ->assertSee('Testable With Layout')
            ->assertDontSee('Testable,');
    });
});
