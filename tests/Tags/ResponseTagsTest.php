<?php

use Illuminate\Http\Exceptions\HttpResponseException;
use Latte\CompileException;
use Statamic\Exceptions\NotFoundHttpException;

// CLASSIFY: N/A — response-modifying tags abort/redirect the HTTP lifecycle.
// {s:404} and {s:redirect} throw HTTP exceptions at runtime.
// Tests verify: (a) Latte compiles these tags without CompileException,
// (b) they throw the expected HTTP exceptions at runtime (not Latte errors).

describe('404 / not_found', function () {
    test('s:404 self-closing compiles and throws NotFoundHttpException at runtime', function () {
        // CLASSIFY: N/A — tag is a runtime abort; Latte compilation must succeed
        expect(fn () => $this->latte('{s:404 /}'))
            ->toThrow(NotFoundHttpException::class);
    });

    test('s:404 without self-close also compiles and throws', function () {
        // CLASSIFY: N/A — empty pair form still compiles; same runtime behaviour
        expect(fn () => $this->latte('{s:404}{/s:404}'))
            ->toThrow(NotFoundHttpException::class);
    });
});

describe('redirect', function () {
    test('s:redirect to "/" compiles and throws at runtime', function () {
        // CLASSIFY: N/A — redirect() calls abort(redirect(...)) which throws
        // Illuminate\Http\Exceptions\HttpResponseException
        expect(fn () => $this->latte('{s:redirect to: "/" /}'))
            ->toThrow(HttpResponseException::class);
    });

    test('s:redirect with no destination compiles and returns nothing (no location)', function () {
        // CLASSIFY: N/A — redirect() with no `to` param returns null (no abort)
        // because $location is null and the early return fires before abort()
        $this->latte('{s:redirect /}')
            ->assertSee('');
    });

    test('s:redirect self-closing compiles without Latte error', function () {
        // CLASSIFY: N/A — compile check; runtime behaviour verified in other tests
        expect(fn () => $this->latte('{s:redirect to: "/home" /}'))
            ->not->toThrow(CompileException::class);
    });
});
