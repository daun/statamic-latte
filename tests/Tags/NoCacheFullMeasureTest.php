<?php

use Statamic\StaticCaching\NoCache\Session;

// Fixtures are created before the first request so the Stache picks up the
// entry when it's built. Kept separate from NoCacheTest, whose beforeEach
// issues a request before any fixtures exist. The layout gets a unique name
// per run because compiled Latte templates persist between test runs.

beforeEach(function () {
    config(['app.key' => 'base64:mLPJYVnk066Xex1MasJvUXpJThbL8Jin1IDSbZ6n/Ns=']);
    config([
        'statamic.static_caching.strategy' => 'full',
        'statamic.static_caching.strategies.full.driver' => 'file',
        'statamic.static_caching.strategies.full.path' => sys_get_temp_dir().'/statamic-latte-static-cache-test',
    ]);

    $this->layout = 'layout-nocache-'.uniqid();

    file_put_contents(fixtures_path("views/{$this->layout}.latte"), implode("\n", [
        '<!DOCTYPE html>',
        '<html lang="en">',
        '<head><title>{block title}Untitled{/block}</title></head>',
        '<body>',
        '<header>{nocache}Dynamic region for: {$page->title}{/nocache}</header>',
        '<main>{block content}{/block}</main>',
        '</body>',
        '</html>',
    ]));

    file_put_contents(fixtures_path('content/collections/pages/testable-nocache.md'), implode("\n", [
        '---',
        'id: 11111111-2222-3333-4444-555555555555',
        'blueprint: page',
        'title: Testable Nocache',
        "layout: {$this->layout}",
        '---',
        'Some content.',
    ]));
});

afterEach(function () {
    @unlink(fixtures_path("views/{$this->layout}.latte"));
    @unlink(fixtures_path('content/collections/pages/testable-nocache.md'));
    exec('rm -rf '.escapeshellarg(sys_get_temp_dir().'/statamic-latte-static-cache-test'));
});

describe('nocache with full-measure static caching', function () {
    test('caches page with placeholder and injected js, then fills region via ajax endpoint', function () {
        // Initial uncached request renders the placeholder and injects Statamic's nocache JS
        $response = $this->get('/testable-nocache');
        $response->assertOk();
        expect($response->getContent())
            ->toContain('<span class="nocache"')
            ->toContain('/!/nocache');

        // The static HTML file is written with the placeholder + JS intact
        $file = glob(sys_get_temp_dir().'/statamic-latte-static-cache-test/*.html')[0] ?? null;
        expect($file)->not->toBeNull();
        expect(file_get_contents($file))
            ->toContain('<span class="nocache"')
            ->toContain('/!/nocache');

        // The ajax request runs in a fresh process: swap in an empty session so
        // regions and cascade must be restored from the cache store
        $url = app(Session::class)->url();
        $this->app->forgetInstance(Session::class);
        $this->app->instance(Session::class, new Session($url));

        $ajax = $this->post('/!/nocache', ['url' => $url]);
        $ajax->assertOk();
        expect(implode('', $ajax->json('regions')))
            ->toContain('Dynamic region for: Testable Nocache');
    });
});
