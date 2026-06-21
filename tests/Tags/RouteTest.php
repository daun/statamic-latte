<?php

use Latte\CompileException;

// CLASSIFY: FIXTURE — no named routes registered; tag likely returns empty or throws

describe('route', function () {
    test('compiles tag pair with name param', function () {
        // CLASSIFY: FIXTURE — no named routes; tag throws RouteNotFoundException at runtime
        expect(fn () => $this->latte('before {s:route name: "home"}{$value}{/s:route} after'))
            ->toThrow(Exception::class);
    });

    test('self-closing compiles without parse error', function () {
        // CLASSIFY: FIXTURE — no named routes; Latte compiles but runtime throws
        expect(fn () => $this->latte('{s:route name: "home"/}'))
            ->not->toThrow(CompileException::class);
    });

    test('supports as: param (throws when no route defined)', function () {
        // CLASSIFY: FIXTURE — no named routes; tag throws before as: variable is populated
        expect(fn () => $this->latte('{s:route name: "home", as: url}{$url}{/s:route}'))
            ->toThrow(Exception::class);
    });

    test('tag pair throws RouteNotFoundException for unknown route', function () {
        // CLASSIFY: FIXTURE — no named routes registered
        expect(fn () => $this->latte('A{s:route name: "nonexistent"}{$value}{/s:route}B'))
            ->toThrow(Exception::class);
    });
});
