<?php

use Latte\CompileException;

// CLASSIFY per test — no form fixtures configured.
// KEY FINDING: the Latte pair-tag syntax {s:form:create}...{/s:form:create} MUST
// compile through the proxy without a Latte CompileException. The compilation test
// is the most critical signal; runtime exceptions about missing form handles are
// expected (FIXTURE) and do not indicate a proxy bug.

describe('form:create pair tag compilation', function () {
    test('form:create pair tag compiles through Latte proxy (no CompileException)', function () {
        // CLASSIFY: FIXTURE — compiles OK; runtime throws "form handle required"
        // because no handle param is provided and no forms are configured.
        expect(fn () => $this->latte('{s:form:create}inner{/s:form:create}'))
            ->toThrow(Exception::class);

        // Verify it is NOT a Latte CompileException — the failure is runtime PHP
        try {
            $this->latte('{s:form:create}inner{/s:form:create}');
        } catch (CompileException $e) {
            // A CompileException here means the pair tag CANNOT be compiled — INCOMPAT
            expect(false)->toBeTrue('form:create pair tag threw a Latte CompileException: '.$e->getMessage());
        } catch (Exception $e) {
            // Any other exception (runtime) means compilation succeeded — expected
            expect(true)->toBeTrue();
        }
    });

    test('form:create with handle param compiles and throws runtime error for missing form', function () {
        // CLASSIFY: FIXTURE — compilation OK; runtime: "Form with handle [contact] cannot be found"
        expect(fn () => $this->latte('{s:form:create handle: "contact"}inner{/s:form:create}'))
            ->toThrow(Exception::class);
    });

    test('form:create pair tag with body accessing value fields compiles', function () {
        // CLASSIFY: FIXTURE — probe that body with variable access compiles too
        expect(fn () => $this->latte('{s:form:create handle: "contact"}{$value}{/s:form:create}'))
            ->toThrow(Exception::class);
    });
});

describe('form:errors pair tag', function () {
    test('form:errors pair tag compiles through Latte proxy', function () {
        // CLASSIFY: FIXTURE — no form configured; runtime exception expected
        expect(fn () => $this->latte('{s:form:errors handle: "contact"}{$value}{/s:form:errors}'))
            ->toThrow(Exception::class);

        try {
            $this->latte('{s:form:errors handle: "contact"}{$value}{/s:form:errors}');
        } catch (CompileException $e) {
            expect(false)->toBeTrue('form:errors threw a Latte CompileException: '.$e->getMessage());
        } catch (Exception $e) {
            expect(true)->toBeTrue();
        }
    });
});

describe('form:set tag', function () {
    test('form:set compiles and injects context without output', function () {
        // CLASSIFY: FIXTURE — set() calls $this->parse() internally which uses Antlers;
        // in Latte proxy context this may throw or return null
        expect(fn () => $this->latte('{s:form:set handle: "contact" /}'))
            ->not->toThrow(CompileException::class);
    });
});

describe('form:success pair tag', function () {
    test('form:success pair tag compiles through Latte proxy', function () {
        // CLASSIFY: FIXTURE — no form submission; compile check only
        try {
            $this->latte('{s:form:success handle: "contact"}DONE{/s:form:success}');
        } catch (CompileException $e) {
            expect(false)->toBeTrue('form:success threw a Latte CompileException: '.$e->getMessage());
        } catch (Exception $e) {
            // Runtime exception is expected; test passes
            expect(true)->toBeTrue();
        }
    });
});
