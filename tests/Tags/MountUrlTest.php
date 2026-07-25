<?php

use Latte\CompileException;

// The pages collection has no mount configured, so the tag returns empty output in these tests.

describe('mount_url', function () {
    test('compiles tag pair for a known collection handle', function () {
        $this->latte('{s:mount_url handle: "pages"}{$value}{/s:mount_url}')
            ->assertSee('');
    });

    test('self-closing compiles without parse error', function () {
        expect(fn () => $this->latte('{s:mount_url handle: "pages"/}'))->not->toThrow(CompileException::class);
    });

    test('supports as: param', function () {
        $this->latte('{s:mount_url handle: "pages", as: mountUrl}{$mountUrl}{/s:mount_url}')
            ->assertSee('');
    });

    test('renders surrounding static content regardless', function () {
        $this->latte('url: [{s:mount_url handle: "pages"/}]')
            ->assertSee('url: [');
    });
});
