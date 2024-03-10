<?php

test('wraps contents of nocache tag', function () {
    $this->latte('A {nocache}B{/nocache}')
        ->assertSee('A <span class="nocache"', false);
});
