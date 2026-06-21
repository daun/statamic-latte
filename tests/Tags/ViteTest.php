<?php

use Latte\CompileException;

// CLASSIFY: FIXTURE — no vite build/manifest; tag throws or returns empty at runtime

describe('vite', function () {
    test('compiles s:vite tag without parse error', function () {
        // CLASSIFY: FIXTURE — no vite build
        expect(fn () => $this->latte('{s:vite src: "resources/js/app.js"/}'))
            ->not->toThrow(CompileException::class);
    });

    test('tag pair body compiles with $value accessible', function () {
        // CLASSIFY: FIXTURE — no vite build
        expect(fn () => $this->latte('{s:vite src: "resources/js/app.js"}{$value}{/s:vite}'))
            ->not->toThrow(CompileException::class);
    });

    test('compiles s:vite:content tag method without parse error', function () {
        // CLASSIFY: FIXTURE — no vite build; tag method syntax must compile
        expect(fn () => $this->latte('{s:vite:content src: "resources/js/app.js"/}'))
            ->not->toThrow(CompileException::class);
    });

    test('multiple src values compile', function () {
        // CLASSIFY: FIXTURE — no vite build
        expect(fn () => $this->latte('{s:vite src: "resources/js/app.js", "resources/css/app.css"/}'))
            ->not->toThrow(CompileException::class);
    });
});
