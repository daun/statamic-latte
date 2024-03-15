<?php

test('renders statamic tags via wrapper', function () {
    $this->latte('<a href="{statamic link to: "fanny-packs"/}"></a>')
        ->assertSee('<a href="/fanny-packs"></a>', false);
});

test('renders statamic tags directly', function () {
    $this->latte('<a href="{link to: "fanny-packs"/}"></a>')
        ->assertSee('<a href="/fanny-packs"></a>', false);
});
