<?php

use Latte\CompileException;

// CLASSIFY: FIXTURE — no asset containers or files; glide tags return empty or throw

describe('glide', function () {
    test('s:glide tag compiles without parse error', function () {
        // CLASSIFY: FIXTURE — no assets
        expect(fn () => $this->latte('{s:glide src: "images/photo.jpg"}{$value}{/s:glide}'))
            ->not->toThrow(CompileException::class);
    });

    test('self-closing s:glide compiles', function () {
        // CLASSIFY: FIXTURE — no assets
        expect(fn () => $this->latte('{s:glide src: "images/photo.jpg", width: 200/}'))
            ->not->toThrow(CompileException::class);
    });

    test('s:glide:data_url tag method compiles', function () {
        // CLASSIFY: FIXTURE — no assets; tag method syntax must parse
        expect(fn () => $this->latte('{s:glide:data_url src: "images/photo.jpg"/}'))
            ->not->toThrow(CompileException::class);
    });

    test('s:glide:batch tag method compiles', function () {
        // CLASSIFY: FIXTURE — no assets
        expect(fn () => $this->latte('{s:glide:batch src: "images/photo.jpg"}{$value}{/s:glide:batch}'))
            ->not->toThrow(CompileException::class);
    });

    test('supports as: param', function () {
        // CLASSIFY: FIXTURE — no assets; variable is empty but compiles
        expect(fn () => $this->latte('{s:glide src: "images/photo.jpg", as: url}{$url}{/s:glide}'))
            ->not->toThrow(CompileException::class);
    });
});
