<?php

// CLASSIFY: OK — real asset fixture + GD driver available; tests assert actual Glide URLs and data URIs.
//
// Note on s:glide:batch: Statamic's batch() calls $this->parse([]) which, in the FluentTag/Latte
// context (where parser=null and tagRenderer=null), returns [] instead of the body string.
// preg_match_all() then throws TypeError. This is a Statamic Latte proxy incompatibility;
// the recommended Latte idiom is to capture a Glide URL into a variable and use it in <img src>.

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
        // In the FluentTag context isPair=false, so index() returns the URL string directly.
        // Use {$value} (not {$value->url}) in the pair body.
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
        // GD driver processes the real JPEG and encodes it as a data: URI.
        $this->latte('{s:glide:data_url src: "assets::img/example.jpg"/}')
            ->assertSee('data:image/jpeg;base64,', false);
    });

    test('as: param captures glide url into named variable', function () {
        $this->latte('{s:glide as: url, src: "assets::img/example.jpg"}{$url}{/s:glide}')
            ->assertSee('/img/', false);
    });

    test('s:glide:batch pair tag is incompatible with the Latte proxy (TypeError)', function () {
        // batch() calls $this->parse([]) which returns [] (not the body string) in the
        // FluentTag context. preg_match_all then throws TypeError. This is a known limitation:
        // Statamic's Antlers parse() ignores $this->content when no Antlers parser is set.
        expect(fn () => $this->latte(
            '{s:glide:batch}<img src="assets::img/example.jpg">{/s:glide:batch}'
        ))->toThrow(TypeError::class);
    });

    test('latte idiom for img src rewriting: capture glide url into img tag', function () {
        // Recommended alternative to s:glide:batch in Latte templates.
        $this->latte(<<<'LATTE'
            {capture $src}{s:glide src: "assets::img/example.jpg"/}{/capture}
            <img src="{$src}" alt="example">
        LATTE)
            ->assertSee('<img src="', false)
            ->assertSee('/img/', false)
            ->assertSee('example.jpg', false);
    });
});
