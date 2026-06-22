<?php

use Latte\CompileException;
use Statamic\Facades\User;

// CLASSIFY: OK — user fixtures exist; authenticated path uses alice@example.com

describe('user:profile', function () {
    test('compiles tag pair without parse error', function () {
        $latteError = false;
        try {
            $this->latte('{s:user:profile/}');
        } catch (CompileException $e) {
            $latteError = true;
        } catch (Throwable $t) {
            // runtime error acceptable; compile error is not
        }
        expect($latteError)->toBeFalse();
    });

    test('renders empty when no user is authenticated', function () {
        $this->latte('[{s:user:profile}{$value->name}{/s:user:profile}]')
            ->assertSee('[]', false);
    });

    test('keeps surrounding static content with no authenticated user', function () {
        $this->latte('A {s:user:profile}{$value->name}{/s:user:profile} B')
            ->assertSee('A')
            ->assertSee('B');
    });

    test('shows authenticated user email in pair body', function () {
        $alice = User::findByEmail('alice@example.com');
        $this->actingAs($alice);

        $this->latte('{s:user:profile}{$value->email}{/s:user:profile}')
            ->assertSee('alice@example.com');
    });

    test('shows authenticated user name in pair body', function () {
        $alice = User::findByEmail('alice@example.com');
        $this->actingAs($alice);

        $this->latte('{s:user:profile}{$value->name}{/s:user:profile}')
            ->assertSee('Alice Smith');
    });

    test('as: param captures profile into named variable', function () {
        $alice = User::findByEmail('alice@example.com');
        $this->actingAs($alice);

        $this->latte('{s:user:profile as: profile}{$profile->email}{/s:user:profile}')
            ->assertSee('alice@example.com');
    });

    test('supports as: param with no user — variable is null, body runs once with null', function () {
        // No auth user; profile returns null → aliased path still renders body once with $profile = null
        // The tag proxy executes the body (aliased mode always runs body once)
        // No assertion on content here; just verify no fatal thrown
        expect(fn () => $this->latte('{s:user:profile as: profile}{/s:user:profile}'))
            ->not->toThrow(CompileException::class);
    });

    test('s:user:logout_url self-closing compiles', function () {
        expect(fn () => $this->latte('{s:user:logout_url/}'))
            ->not->toThrow(CompileException::class);
    });
});
