<?php

test('caches contents of cache tag', function () {
    // config(['statamic.static_caching.strategy' => 'half']);

    $this->latte('{$var} {cache}{$var}{/cache}', ['var' => 'A'])
        ->assertSee('A A');
    $this->latte('{$var} {cache}{$var}{/cache}', ['var' => 'B'])
        ->assertSee('B A');
});

test('respects statamic cache tag config', function () {
    config(['statamic.system.cache_tags_enabled' => false]);

    $this->latte('{$var} {cache}{$var}{/cache}', ['var' => 'A'])
        ->assertSee('A A');
    $this->latte('{$var} {cache}{$var}{/cache}', ['var' => 'B'])
        ->assertSee('B B');
});

test('only caches get requests', function () {
    request()->setMethod('POST');

    $this->latte('{$var} {cache}{$var}{/cache}', ['var' => 'A'])
        ->assertSee('A A');
    $this->latte('{$var} {cache}{$var}{/cache}', ['var' => 'B'])
        ->assertSee('B B');
});

test('allow conditional caching using if param', function () {
    $this->latte('{$var} {cache}{$var}{/cache}', ['var' => 'A'])
        ->assertSee('A A');
    $this->latte('{$var} {cache}{$var}{/cache}', ['var' => 'B'])
        ->assertSee('B A');
    $this->latte('{$var} {cache if: false}{$var}{/cache}', ['var' => 'C'])
        ->assertSee('C C');
});
