<?php

use Statamic\Facades\Entry;
use Statamic\StaticCaching\NoCache\Session;

// Fixtures are created before the first request so the Stache picks up the
// entries when it's built. The layout gets a unique name per run because
// compiled Latte templates persist between test runs.

beforeEach(function () {
    config(['app.key' => 'base64:mLPJYVnk066Xex1MasJvUXpJThbL8Jin1IDSbZ6n/Ns=']);
    config(['statamic.static_caching.strategy' => null]);

    $this->layout = 'layout-nested-cache-'.uniqid();

    foreach (['aaa' => 'Page A', 'bbb' => 'Page B'] as $slug => $title) {
        file_put_contents(fixtures_path("content/collections/pages/testable-nested-{$slug}.md"), implode("\n", [
            '---',
            "id: nested-cache-test-{$slug}",
            'blueprint: page',
            "title: {$title}",
            "layout: {$this->layout}",
            '---',
            'Some content.',
        ]));
    }
});

afterEach(function () {
    @unlink(fixtures_path("views/{$this->layout}.latte"));
    @unlink(fixtures_path('content/collections/pages/testable-nested-aaa.md'));
    @unlink(fixtures_path('content/collections/pages/testable-nested-bbb.md'));
});

function freshNocacheSession(): void
{
    app()->forgetInstance(Session::class);
}

describe('nocache inside cache', function () {
    test('stays dynamic when the fragment is replayed from cache', function () {
        file_put_contents(fixtures_path("views/{$this->layout}.latte"), implode("\n", [
            '<body>',
            '<p>Outside: {$page->title}</p>',
            '{cache}',
            '<p>Cached: {$page->title}</p>',
            '{nocache}<p>Dynamic: {$page->title}</p>{/nocache}',
            '{/cache}',
            '</body>',
        ]));

        $response = $this->get('/testable-nested-aaa');
        $response->assertOk();
        expect($response->getContent())
            ->toContain('Outside: Page A')
            ->toContain('Cached: Page A')
            ->toContain('Dynamic: Page A');

        Entry::find('nested-cache-test-aaa')->set('title', 'Updated')->saveQuietly();
        freshNocacheSession();

        $response = $this->get('/testable-nested-aaa');
        $response->assertOk();
        expect($response->getContent())
            ->toContain('Outside: Updated')
            ->toContain('Cached: Page A')
            ->toContain('Dynamic: Updated');
    });

    test('re-registers regions when a shared fragment is replayed on another url', function () {
        file_put_contents(fixtures_path("views/{$this->layout}.latte"), implode("\n", [
            '<body>',
            '{cache}',
            '<p>Cached shared fragment</p>',
            '{nocache}<p>Dynamic: {$page->title}</p>{/nocache}',
            '{/cache}',
            '</body>',
        ]));

        $a = $this->get('/testable-nested-aaa');
        $a->assertOk();
        expect($a->getContent())->toContain('Dynamic: Page A');

        freshNocacheSession();

        $b = $this->get('/testable-nested-bbb');
        $b->assertOk();
        expect($b->getContent())->toContain('Dynamic: Page B');
    });

    test('keeps region keys stable around a replayed fragment', function () {
        // Without re-pushing, the outside region would shift onto the replayed
        // region's key and clobber it
        file_put_contents(fixtures_path("views/{$this->layout}.latte"), implode("\n", [
            '<body>',
            '{cache}',
            '{nocache}<p>Inside: {$page->title}</p>{/nocache}',
            '{/cache}',
            '{nocache}<p>Outside: {$page->title}</p>{/nocache}',
            '</body>',
        ]));

        $response = $this->get('/testable-nested-aaa');
        $response->assertOk();
        expect($response->getContent())
            ->toContain('Inside: Page A')
            ->toContain('Outside: Page A');

        Entry::find('nested-cache-test-aaa')->set('title', 'Updated')->saveQuietly();
        freshNocacheSession();

        $response = $this->get('/testable-nested-aaa');
        $response->assertOk();
        expect($response->getContent())
            ->toContain('Inside: Updated')
            ->toContain('Outside: Updated');
    });
});

describe('cache inside nocache', function () {
    test('keeps the region dynamic and the inner fragment cached', function () {
        file_put_contents(fixtures_path("views/{$this->layout}.latte"), implode("\n", [
            '<body>',
            '{nocache}',
            '<p>Dynamic: {$page->title}</p>',
            '{cache}<p>Cached: {$page->title}</p>{/cache}',
            '{/nocache}',
            '</body>',
        ]));

        $response = $this->get('/testable-nested-aaa');
        $response->assertOk();
        expect($response->getContent())
            ->toContain('Dynamic: Page A')
            ->toContain('Cached: Page A');

        Entry::find('nested-cache-test-aaa')->set('title', 'Updated')->saveQuietly();
        freshNocacheSession();

        $response = $this->get('/testable-nested-aaa');
        $response->assertOk();
        expect($response->getContent())
            ->toContain('Dynamic: Updated')
            ->toContain('Cached: Page A');
    });
});
