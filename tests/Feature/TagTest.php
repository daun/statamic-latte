<?php

test('provides statamic() helper function', function () {
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

test('provides s() helper function as alias', function () {
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
