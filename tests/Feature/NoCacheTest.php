<?php

beforeEach(function () {
    // Statamic's cache won't do anything if it's not being used on a request with the cache middleware
    config(['app.key' => 'base64:mLPJYVnk066Xex1MasJvUXpJThbL8Jin1IDSbZ6n/Ns=']);
    $this->get('/');
});

test('wraps contents of nocache tag', function () {
    $this->latte('A {nocache}B{/nocache}')
        ->assertSee('A <span class="nocache"', false);
});
