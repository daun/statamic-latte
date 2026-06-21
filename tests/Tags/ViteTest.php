<?php

use Illuminate\Foundation\Vite;

// CLASSIFY: OK — vite build fixtures under tests/fixtures/vite/; tests assert real asset tag output

describe('vite', function () {
    beforeEach(function () {
        // Point public_path() at the vite fixture directory so Vite finds the build manifest.
        $this->app->usePublicPath(fixtures_path('vite'));

        // Clear the static manifest cache so each test starts clean.
        $ref = new ReflectionProperty(Vite::class, 'manifests');
        $ref->setAccessible(true);
        $ref->setValue(null, []);
    });

    test('emits script tag with manifested filename', function () {
        $this->latte('{s:vite src: "resources/js/app.js"/}')
            ->assertSee('app-abc123.js', false)
            ->assertSee('<script', false);
    });

    test('emits modulepreload link for js entry', function () {
        $this->latte('{s:vite src: "resources/js/app.js"/}')
            ->assertSee('modulepreload', false)
            ->assertSee('app-abc123.js', false);
    });

    test('vite:content returns built file contents', function () {
        $this->latte('{s:vite:content src: "resources/js/app.js"/}')
            ->assertSee('console.log("hello vite")', false);
    });

    test('throws when src is missing from manifest', function () {
        expect(fn () => $this->latte('{s:vite src: "resources/js/missing.js"/}'))
            ->toThrow(Exception::class);
    });
});
