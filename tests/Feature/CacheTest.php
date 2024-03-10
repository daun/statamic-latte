<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

test('caches contents of cache tag', function () {
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

test('supports defining cache duration', function () {
    $this->latte('{cache for:"1 minute"}{$var}{/cache}', ['var' => 'A'])->assertSee('A');
    $this->latte('{cache for:"1 minute"}{$var}{/cache}', ['var' => 'B'])->assertSee('A');

    Carbon::setTestNow(now());
    $this->latte('{cache for:"2 hours"}{$var}{/cache}', ['var' => 'A'])->assertSee('A');
    Carbon::setTestNow(now()->addHours(1));
    $this->latte('{cache for:"2 hours"}{$var}{/cache}', ['var' => 'A'])->assertSee('A');
    Carbon::setTestNow(now()->addHours(3));
    $this->latte('{cache for:"2 hours"}{$var}{/cache}', ['var' => 'B'])->assertSee('B');
});

test('supports tagging cache entries', function () {
    $this->latte('{cache tags:["a"]}{$var}{/cache}', ['var' => 'A'])->assertSee('A');
    $this->latte('{cache tags:["a"]}{$var}{/cache}', ['var' => 'B'])->assertSee('A');
    Cache::tags('a')->flush();
    $this->latte('{cache tags:["a"]}{$var}{/cache}', ['var' => 'B'])->assertSee('B');
});
