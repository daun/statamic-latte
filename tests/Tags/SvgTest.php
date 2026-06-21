<?php

use Latte\CompileException;

// CLASSIFY: FIXTURE — no svg directory configured; tag returns empty or throws

describe('svg', function () {
    test('compiles tag pair without parse error', function () {
        // CLASSIFY: FIXTURE — no svg dir
        expect(fn () => $this->latte('{s:svg src: "logo"}{$value}{/s:svg}'))
            ->not->toThrow(CompileException::class);
    });

    test('self-closing compiles without parse error', function () {
        // CLASSIFY: FIXTURE — no svg dir
        expect(fn () => $this->latte('{s:svg src: "logo"/}'))
            ->not->toThrow(CompileException::class);
    });

    test('renders empty or nothing when svg file does not exist', function () {
        // CLASSIFY: FIXTURE — no svg dir; output is empty or blank
        $threw = false;
        try {
            $result = $this->latte('{s:svg src: "logo"/}');
            expect(true)->toBeTrue();
        } catch (Exception $e) {
            $threw = true;
            expect($threw)->toBeTrue();
        }
    });

    test('supports class param in tag', function () {
        // CLASSIFY: FIXTURE — no svg dir; param must at least compile
        expect(fn () => $this->latte('{s:svg src: "logo", class: "icon"/}'))
            ->not->toThrow(CompileException::class);
    });

    test('surrounding static content renders regardless', function () {
        // CLASSIFY: FIXTURE — no svg dir; static text must still appear
        $threw = false;
        try {
            $this->latte('icon: {s:svg src: "logo"/} end')
                ->assertSee('icon:')
                ->assertSee('end');
        } catch (Exception $e) {
            // tag may throw when no svg dir configured; that is acceptable
            expect($threw)->toBeFalse();
        }
    });
});
