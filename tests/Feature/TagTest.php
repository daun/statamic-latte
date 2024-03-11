<?php

test('renders statamic tags', function () {
    $this->latte('{statamic link to:"fanny-packs"}')
        ->assertSee('statamic:link');
});
