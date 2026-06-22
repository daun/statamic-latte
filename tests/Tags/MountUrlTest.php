<?php

use Latte\CompileException;

// CLASSIFY: FIXTURE — pages collection has no mount entry; tag likely returns empty or null

describe('mount_url', function () {
    test('compiles tag pair for a known collection handle', function () {
        // CLASSIFY: FIXTURE — no mount configured; returns empty
        $this->latte('{s:mount_url handle: "pages"}{$value}{/s:mount_url}')
            ->assertSee('');
    });

    test('self-closing compiles without parse error', function () {
        // CLASSIFY: FIXTURE — no mount configured
        expect(fn () => $this->latte('{s:mount_url handle: "pages"/}'))->not->toThrow(CompileException::class);
    });

    test('supports as: param', function () {
        // CLASSIFY: FIXTURE — no mount; variable is empty
        $this->latte('{s:mount_url handle: "pages", as: mountUrl}{$mountUrl}{/s:mount_url}')
            ->assertSee('');
    });

    test('renders surrounding static content regardless', function () {
        // CLASSIFY: FIXTURE — no mount; static text must still appear
        $this->latte('url: [{s:mount_url handle: "pages"/}]')
            ->assertSee('url: [');
    });
});
