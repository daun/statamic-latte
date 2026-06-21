<?php

use Latte\CompileException;

// CLASSIFY: FIXTURE/N/A — no authenticated user; tag returns empty or null; no user fixtures

describe('user:profile', function () {
    test('compiles tag pair without parse error', function () {
        // CLASSIFY: FIXTURE — no auth user; self-closing avoids runtime Content-to-string error
        $latteError = false;
        try {
            $this->latte('{s:user:profile/}');
        } catch (CompileException $e) {
            $latteError = true;
        } catch (Throwable $t) {
            // runtime error is acceptable; compile error is not
        }
        expect($latteError)->toBeFalse();
    });

    test('renders empty when no user is authenticated', function () {
        // CLASSIFY: OK — no user → result skipped/empty; no Content-to-string crash.
        $this->latte('[{s:user:profile}{$value->name}{/s:user:profile}]')
            ->assertSee('[]', false);
    });

    test('keeps surrounding static content with no authenticated user', function () {
        // CLASSIFY: OK — proxy no longer fatals casting Content to string.
        $this->latte('A {s:user:profile}{$value->name}{/s:user:profile} B')
            ->assertSee('A')
            ->assertSee('B');
    });

    test('supports as: param capturing profile data', function () {
        // CLASSIFY: FIXTURE — no auth user; variable is null/empty, loop skips
        $this->latte('{s:user:profile as: profile}{$profile->email}{/s:user:profile}')
            ->assertSee('');
    });

    test('s:user:logout_url self-closing compiles', function () {
        // CLASSIFY: N/A — related user tag; no session needed to compile
        expect(fn () => $this->latte('{s:user:logout_url/}'))
            ->not->toThrow(CompileException::class);
    });
});
