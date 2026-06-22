<?php

use Statamic\Facades\Stache;

// CLASSIFY: OK — assets container + real image fixture in place; tests assert actual asset data

describe('assets', function () {
    beforeEach(function () {
        Stache::clear();
    });

    test('iterates assets in a container and exposes basename', function () {
        $this->latte('{s:assets container: "assets"}{$value->basename}{sep}, {/sep}{/s:assets}')
            ->assertSee('example.jpg');
    });

    test('exposes asset url via value variable', function () {
        $this->latte('{s:assets container: "assets"}{$value->url}{sep}, {/sep}{/s:assets}')
            ->assertSee('/assets/img/example.jpg', false);
    });

    test('as: param captures asset collection into named variable', function () {
        $this->latte('{s:assets as: files, container: "assets"}{foreach $files as $f}{$f->basename}{sep}, {/sep}{/foreach}{/s:assets}')
            ->assertSee('example.jpg');
    });

    test('folder filter narrows results to matching subdirectory', function () {
        $this->latte('{s:assets container: "assets", folder: "img"}{$value->basename}{/s:assets}')
            ->assertSee('example.jpg');
    });

    test('returns no output when folder has no matching assets', function () {
        $this->latte('[{s:assets container: "assets", folder: "nonexistent"}{$value->basename}{/s:assets}]')
            ->assertSee('[]', false);
    });

    test('extension property reflects the file type', function () {
        $this->latte('{s:assets container: "assets"}{$value->extension}{sep}, {/sep}{/s:assets}')
            ->assertSee('jpg');
    });
});
