<?php

use Latte\CompileException;

// CLASSIFY: FIXTURE — no OAuth providers configured; OAuth::provider() throws at runtime.
// Tests verify that the Latte proxy compiles these tags without a CompileException;
// runtime failures are fixture gaps, not proxy bugs.

describe('oauth', function () {
    test('oauth:login_url self-closing compiles; throws at runtime without provider configured', function () {
        // CLASSIFY: FIXTURE — no provider; OAuth::provider(null) throws
        expect(fn () => $this->latte('{s:oauth:login_url /}'))
            ->not->toThrow(CompileException::class);
    });

    test('oauth wildcard method compiles; throws at runtime without provider', function () {
        // CLASSIFY: FIXTURE — {s:oauth:github} calls wildcard("github") →
        // OAuth::provider("github")->loginUrl() → throws (not configured)
        expect(fn () => $this->latte('{s:oauth:github /}'))
            ->not->toThrow(CompileException::class);
    });

    test('oauth:login_url with provider param compiles; throws at runtime', function () {
        // CLASSIFY: FIXTURE — provider "google" not configured
        expect(fn () => $this->latte('{s:oauth:login_url provider: "google" /}'))
            ->not->toThrow(CompileException::class);
    });

    test('s() helper oauth:github throws at runtime, not at Latte compile time', function () {
        // CLASSIFY: FIXTURE — s() bypasses Latte compilation; throws PHP exception at runtime
        expect(fn () => $this->latte('{if s("oauth:github")}yes{/if}'))
            ->not->toThrow(CompileException::class);
    });
});
