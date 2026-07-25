<?php

use Latte\CompileException;

// No OAuth providers are configured, so these tags throw at runtime; the tests only verify they compile without a CompileException.

describe('oauth', function () {
    test('oauth:login_url self-closing compiles; throws at runtime without provider configured', function () {
        expect(fn () => $this->latte('{s:oauth:login_url /}'))
            ->not->toThrow(CompileException::class);
    });

    test('oauth wildcard method compiles; throws at runtime without provider', function () {
        expect(fn () => $this->latte('{s:oauth:github /}'))
            ->not->toThrow(CompileException::class);
    });

    test('oauth:login_url with provider param compiles; throws at runtime', function () {
        expect(fn () => $this->latte('{s:oauth:login_url provider: "google" /}'))
            ->not->toThrow(CompileException::class);
    });

    test('s() helper oauth:github throws at runtime, not at Latte compile time', function () {
        expect(fn () => $this->latte('{if s("oauth:github")}yes{/if}'))
            ->not->toThrow(CompileException::class);
    });
});
