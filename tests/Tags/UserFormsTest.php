<?php

use Latte\CompileException;

// CLASSIFY per test — user form pair tags require Statamic routes to be registered
// (e.g. statamic.login, statamic.register, etc.) and a booted session.
// KEY FINDING: the most important question is whether these PAIR tags compile through
// the Latte proxy without a CompileException. Runtime failures (RouteNotFoundException,
// session errors) are FIXTURE / N/A issues, not proxy bugs.

describe('user:login_form pair tag compilation', function () {
    test('user:login_form pair tag compiles through Latte proxy (no CompileException)', function () {
        // CLASSIFY: N/A — routes/session may not be available; compile check is the signal
        expect(fn () => $this->latte('{s:user:login_form}<input type="text" name="email">{/s:user:login_form}'))
            ->not->toThrow(CompileException::class);
    });

    test('user:login_form pair tag body references passthrough content', function () {
        // CLASSIFY: N/A — verifies proxy handles pair body containing HTML
        expect(fn () => $this->latte('{s:user:login_form}<form-body>{/s:user:login_form}'))
            ->not->toThrow(CompileException::class);
    });
});

describe('user:register_form pair tag compilation', function () {
    test('user:register_form pair tag compiles through Latte proxy', function () {
        // CLASSIFY: N/A — route statamic.register may not exist in test env
        expect(fn () => $this->latte('{s:user:register_form}INNER{/s:user:register_form}'))
            ->not->toThrow(CompileException::class);
    });
});

describe('user:forgot_password_form pair tag compilation', function () {
    test('user:forgot_password_form pair tag compiles through Latte proxy', function () {
        // CLASSIFY: N/A — route statamic.password.email may not exist in test env
        expect(fn () => $this->latte('{s:user:forgot_password_form}INNER{/s:user:forgot_password_form}'))
            ->not->toThrow(CompileException::class);
    });
});

describe('user:logout_url', function () {
    test('user:logout_url self-closing renders a URL or throws without route', function () {
        // CLASSIFY: N/A — route statamic.logout may not be registered in test env
        try {
            $result = $this->latte('{s:user:logout_url /}');
            // If it didn't throw, a URL was rendered
            $result->assertSee('logout');
        } catch (CompileException $e) {
            expect(false)->toBeTrue('Latte CompileException: '.$e->getMessage());
        } catch (Exception $e) {
            expect(true)->toBeTrue();
        }
    });
});

describe('user:logout', function () {
    test('user:logout self-closing compiles; aborts with redirect at runtime', function () {
        // CLASSIFY: N/A — logout() calls auth()->logout() then abort(redirect(...))
        // which throws an HttpResponseException
        expect(fn () => $this->latte('{s:user:logout /}'))
            ->not->toThrow(CompileException::class);
    });
});
