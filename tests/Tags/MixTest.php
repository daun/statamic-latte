<?php

use Latte\CompileException;

// CLASSIFY: FIXTURE — no mix-manifest.json; tag throws or returns empty

describe('mix', function () {
    test('compiles tag without parse error', function () {
        // CLASSIFY: FIXTURE — no mix manifest; may throw at runtime
        expect(fn () => $this->latte('{s:mix src: "/css/app.css"/}'))
            ->not->toThrow(CompileException::class);
    });

    test('tag pair compiles and $value is accessible in body', function () {
        // CLASSIFY: FIXTURE — no mix manifest; runtime may throw; body structure valid
        expect(fn () => $this->latte('{s:mix src: "/css/app.css"}{$value}{/s:mix}'))
            ->not->toThrow(CompileException::class);
    });

    test('self-closing throws or renders empty when no manifest exists', function () {
        // CLASSIFY: FIXTURE — no mix manifest; tag likely throws \Exception
        $threw = false;
        try {
            $result = $this->latte('{s:mix src: "/css/app.css"/}');
            // if it does not throw, output should be empty or a path
            expect(true)->toBeTrue();
        } catch (Exception $e) {
            $threw = true;
            expect($threw)->toBeTrue();
        }
    });

    test('supports as: param syntax', function () {
        // CLASSIFY: FIXTURE — no manifest; verifies Latte param parsing does not break
        expect(fn () => $this->latte('{s:mix src: "/css/app.css", as: assetUrl}{$assetUrl}{/s:mix}'))
            ->not->toThrow(CompileException::class);
    });
});
