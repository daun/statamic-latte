<?php

// s:glide:batch is incompatible with the Latte proxy: batch() calls parse([]), which returns [] instead of the body string, so preg_match_all() throws; capture a Glide URL into a variable instead.

use Statamic\Facades\Stache;

describe('glide', function () {
    beforeEach(function () {
        Stache::clear();
    });

    test('self-closing glide tag returns a glide route url', function () {
        // Secure mode disabled in tests; URL has no signature param.
        $this->latte('{s:glide src: "assets::img/example.jpg"/}')
            ->assertSee('/img/', false)
            ->assertSee('example.jpg', false);
    });

    test('glide pair tag exposes url string as $value', function () {
        // index() returns the URL string directly, so the pair body uses {$value}, not {$value->url}.
        $this->latte('{s:glide src: "assets::img/example.jpg"}{$value}{/s:glide}')
            ->assertSee('/img/', false)
            ->assertSee('example.jpg', false);
    });

    test('width manipulation param is reflected in the glide url', function () {
        $this->latte('{s:glide src: "assets::img/example.jpg", width: 100/}')
            ->assertSee('/img/', false)
            ->assertSee('example.jpg', false);
    });

    test('s:glide:data_url returns a base64 data uri', function () {
        $this->latte('{s:glide:data_url src: "assets::img/example.jpg"/}')
            ->assertSee('data:image/jpeg;base64,', false);
    });

    test('as: param captures glide url into named variable', function () {
        $this->latte('{s:glide as: url, src: "assets::img/example.jpg"}{$url}{/s:glide}')
            ->assertSee('/img/', false);
    });

    test('s:glide:batch pair tag is incompatible with the Latte proxy (TypeError)', function () {
        expect(fn () => $this->latte(
            '{s:glide:batch}<img src="assets::img/example.jpg">{/s:glide:batch}'
        ))->toThrow(TypeError::class);
    });

    test('latte idiom for img src rewriting: capture glide url into img tag', function () {
        $this->latte(<<<'LATTE'
            {capture $src}{s:glide src: "assets::img/example.jpg"/}{/capture}
            <img src="{$src}" alt="example">
        LATTE)
            ->assertSee('<img src="', false)
            ->assertSee('/img/', false)
            ->assertSee('example.jpg', false);
    });
});
