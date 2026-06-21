<?php

// CLASSIFY: OK — real search over pages collection via local (Comb) driver; sync queue

use Illuminate\Support\Facades\File;
use Statamic\Facades\Search;

$tmpSearchPath = null;

beforeEach(function () use (&$tmpSearchPath) {
    $tmpSearchPath = sys_get_temp_dir().'/statamic-latte-search-'.uniqid();

    config([
        'statamic.search.default' => 'default',
        'statamic.search.indexes' => [
            'default' => [
                'driver' => 'local',
                'searchables' => ['collection:pages'],
                'fields' => ['title'],
            ],
        ],
        'statamic.search.drivers.local' => [
            'path' => $tmpSearchPath,
        ],
    ]);

    // Build index synchronously — Testbench uses sync queue driver by default
    Search::index('default')->update();
});

afterEach(function () use (&$tmpSearchPath) {
    if ($tmpSearchPath && is_dir($tmpSearchPath)) {
        File::deleteDirectory($tmpSearchPath);
    }
    $tmpSearchPath = null;
});

describe('search', function () {
    test('for: param returns published entries matching query', function () {
        $this->latte('{s:search:results for: "Testable"}{$value->title}{sep}, {/sep}{/s:search:results}')
            ->assertSee('Testable');
    });

    test('as: param captures results; foreach iterates them', function () {
        $this->latte('{s:search:results as: hits, for: "Testable"}{foreach $hits as $h}{$h->title}{sep} | {/sep}{/foreach}{/s:search:results}')
            ->assertSee('Testable')
            ->assertSee('Testable With Layout');
    });

    test('for: param with specific term narrows to the matching entry', function () {
        $this->latte('{s:search:results for: "Layout"}{$value->title}{/s:search:results}')
            ->assertSee('Testable With Layout');
    });

    test('for: param with no-match query yields empty output', function () {
        $this->latte('{s:search:results for: "zzznotfound"}{$value->title}{/s:search:results}')
            ->assertDontSee('Testable');
    });
});
