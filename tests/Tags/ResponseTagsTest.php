<?php

use Illuminate\Http\Exceptions\HttpResponseException;
use Latte\CompileException;
use Statamic\Exceptions\NotFoundHttpException;

// Response-modifying tags abort the HTTP lifecycle: {s:404} and {s:redirect} throw HTTP
// exceptions at runtime rather than rendering output.

describe('404 / not_found', function () {
    test('s:404 self-closing compiles and throws NotFoundHttpException at runtime', function () {
        expect(fn () => $this->latte('{s:404 /}'))
            ->toThrow(NotFoundHttpException::class);
    });

    test('s:404 without self-close also compiles and throws', function () {
        expect(fn () => $this->latte('{s:404}{/s:404}'))
            ->toThrow(NotFoundHttpException::class);
    });
});

describe('redirect', function () {
    test('s:redirect to "/" compiles and throws at runtime', function () {
        expect(fn () => $this->latte('{s:redirect to: "/" /}'))
            ->toThrow(HttpResponseException::class);
    });

    test('s:redirect with no destination compiles and returns nothing (no location)', function () {
        // With no `to:` param, redirect() returns early without aborting.
        $this->latte('{s:redirect /}')
            ->assertSee('');
    });

    test('s:redirect self-closing compiles without Latte error', function () {
        expect(fn () => $this->latte('{s:redirect to: "/home" /}'))
            ->not->toThrow(CompileException::class);
    });
});
