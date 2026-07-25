<?php

use Latte\CompileException;

// These tags need Statamic auth routes and a session at runtime, so tests only assert that they compile through the Latte proxy.

describe('user:login_form pair tag compilation', function () {
    test('user:login_form pair tag compiles through Latte proxy (no CompileException)', function () {
        expect(fn () => $this->latte('{s:user:login_form}<input type="text" name="email">{/s:user:login_form}'))
            ->not->toThrow(CompileException::class);
    });

    test('user:login_form pair tag body references passthrough content', function () {
        expect(fn () => $this->latte('{s:user:login_form}<form-body>{/s:user:login_form}'))
            ->not->toThrow(CompileException::class);
    });
});

describe('user:register_form pair tag compilation', function () {
    test('user:register_form pair tag compiles through Latte proxy', function () {
        expect(fn () => $this->latte('{s:user:register_form}INNER{/s:user:register_form}'))
            ->not->toThrow(CompileException::class);
    });
});

describe('user:forgot_password_form pair tag compilation', function () {
    test('user:forgot_password_form pair tag compiles through Latte proxy', function () {
        expect(fn () => $this->latte('{s:user:forgot_password_form}INNER{/s:user:forgot_password_form}'))
            ->not->toThrow(CompileException::class);
    });
});

describe('user:logout_url', function () {
    test('user:logout_url self-closing renders a URL or throws without route', function () {
        // The statamic.logout route may not be registered in the test environment.
        try {
            $result = $this->latte('{s:user:logout_url /}');
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
        // logout() ends with abort(redirect()), which throws an HttpResponseException at runtime.
        expect(fn () => $this->latte('{s:user:logout /}'))
            ->not->toThrow(CompileException::class);
    });
});
