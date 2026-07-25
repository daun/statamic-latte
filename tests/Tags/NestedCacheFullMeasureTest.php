<?php

use Statamic\Facades\Entry;
use Statamic\StaticCaching\NoCache\Session;

// Fixtures are created before the first request so the Stache picks up the
// entry when it's built. The layout gets a unique name per run because
// compiled Latte templates persist between test runs.

beforeEach(function () {
    config(['app.key' => 'base64:mLPJYVnk066Xex1MasJvUXpJThbL8Jin1IDSbZ6n/Ns=']);
    config([
        'statamic.static_caching.strategy' => 'full',
        'statamic.static_caching.strategies.full.driver' => 'file',
        'statamic.static_caching.strategies.full.path' => sys_get_temp_dir().'/statamic-latte-static-cache-nested-test',
    ]);

    $this->layout = 'layout-nested-full-'.uniqid();

    file_put_contents(fixtures_path("views/{$this->layout}.latte"), implode("\n", [
        '<!DOCTYPE html>',
        '<html lang="en">',
        '<head><title>{block title}Untitled{/block}</title></head>',
        '<body>',
        '{cache}',
        '<p>Cached: {$page->title}</p>',
        '{nocache}<p>Dynamic: {$page->title}</p>{/nocache}',
        '{/cache}',
        '</body>',
        '</html>',
    ]));

    file_put_contents(fixtures_path('content/collections/pages/testable-nested-full.md'), implode("\n", [
        '---',
        'id: nested-cache-test-full',
        'blueprint: page',
        'title: Existing',
        "layout: {$this->layout}",
        '---',
        'Some content.',
    ]));
});

afterEach(function () {
    @unlink(fixtures_path("views/{$this->layout}.latte"));
    @unlink(fixtures_path('content/collections/pages/testable-nested-full.md'));
    exec('rm -rf '.escapeshellarg(sys_get_temp_dir().'/statamic-latte-static-cache-nested-test'));
});

describe('nocache inside cache with full-measure static caching', function () {
    test('caches page with placeholder, then fills region via ajax endpoint', function () {
        $response = $this->get('/testable-nested-full');
        $response->assertOk();
        expect($response->getContent())
            ->toContain('Cached: Existing')
            ->toContain('<span class="nocache"');

        $file = glob(sys_get_temp_dir().'/statamic-latte-static-cache-nested-test/*.html')[0] ?? null;
        expect($file)->not->toBeNull();
        expect(file_get_contents($file))->toContain('<span class="nocache"');

        // The ajax request runs in a fresh process: swap in an empty session so
        // regions and cascade must be restored from the cache store
        Entry::find('nested-cache-test-full')->set('title', 'Updated')->saveQuietly();

        $url = app(Session::class)->url();
        $this->app->forgetInstance(Session::class);
        $this->app->instance(Session::class, new Session($url));

        $ajax = $this->post('/!/nocache', ['url' => $url]);
        $ajax->assertOk();
        expect(implode('', $ajax->json('regions')))
            ->toContain('Dynamic: Updated');
    });

    test('re-render against a warm fragment cache keeps the region resolvable', function () {
        $this->get('/testable-nested-full')->assertOk();

        // Invalidate the static page but keep the fragment cache warm
        exec('rm -rf '.escapeshellarg(sys_get_temp_dir().'/statamic-latte-static-cache-nested-test'));
        app()->forgetInstance(Session::class);

        Entry::find('nested-cache-test-full')->set('title', 'Updated')->saveQuietly();

        $response = $this->get('/testable-nested-full');
        $response->assertOk();
        expect($response->getContent())
            ->toContain('Cached: Existing')
            ->toContain('<span class="nocache"');

        $url = app(Session::class)->url();
        $this->app->forgetInstance(Session::class);
        $this->app->instance(Session::class, new Session($url));

        $ajax = $this->post('/!/nocache', ['url' => $url]);
        $ajax->assertOk();
        expect(implode('', $ajax->json('regions')))
            ->toContain('Dynamic: Updated');
    });
});
