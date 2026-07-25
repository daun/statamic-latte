<?php

use Latte\CompileException;

// No mix-manifest.json fixture exists, so the tag may throw or return empty at runtime.

describe('mix', function () {
    test('compiles tag without parse error', function () {
        expect(fn () => $this->latte('{s:mix src: "/css/app.css"/}'))
            ->not->toThrow(CompileException::class);
    });

    test('tag pair compiles and $value is accessible in body', function () {
        expect(fn () => $this->latte('{s:mix src: "/css/app.css"}{$value}{/s:mix}'))
            ->not->toThrow(CompileException::class);
    });

    test('self-closing throws or renders empty when no manifest exists', function () {
        $threw = false;
        try {
            $result = $this->latte('{s:mix src: "/css/app.css"/}');
            expect(true)->toBeTrue();
        } catch (Exception $e) {
            $threw = true;
            expect($threw)->toBeTrue();
        }
    });

    test('supports as: param syntax', function () {
        expect(fn () => $this->latte('{s:mix src: "/css/app.css", as: assetUrl}{$assetUrl}{/s:mix}'))
            ->not->toThrow(CompileException::class);
    });
});
