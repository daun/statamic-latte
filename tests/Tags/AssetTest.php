<?php

use Statamic\Facades\Stache;

// CLASSIFY: OK — assets container + real image fixture in place; tests assert actual asset data

describe('asset', function () {
    beforeEach(function () {
        // Stache needs to be warm before asset lookups; clear any stale Stache cache.
        Stache::clear();
    });

    test('finds asset by id and exposes its url', function () {
        $this->latte('{s:asset url: "assets::img/example.jpg"}{$value->url}{/s:asset}')
            ->assertSee('/assets/img/example.jpg', false);
    });

    test('exposes asset extension via pair body', function () {
        $this->latte('{s:asset url: "assets::img/example.jpg"}{$value->extension}{/s:asset}')
            ->assertSee('jpg');
    });

    test('exposes asset path via pair body', function () {
        $this->latte('{s:asset url: "assets::img/example.jpg"}{$value->path}{/s:asset}')
            ->assertSee('img/example.jpg', false);
    });

    test('pair body is skipped when asset path is unknown (null result skips body)', function () {
        // CLASSIFY: OK — unknown path returns null; TagNode skips pair body, outputs nothing.
        $this->latte('[{s:asset url: "assets::img/nonexistent.jpg"}{$value->url}{/s:asset}]')
            ->assertSee('[]', false);
    });

    test('supports as: param to capture asset into named variable', function () {
        $this->latte('{s:asset as: img, url: "assets::img/example.jpg"}{$img->extension}{/s:asset}')
            ->assertSee('jpg');
    });

    test('renders surrounding static content alongside asset', function () {
        $this->latte('ext: {s:asset url: "assets::img/example.jpg"}{$value->extension}{/s:asset} end')
            ->assertSee('ext:')
            ->assertSee('jpg')
            ->assertSee('end');
    });
});
