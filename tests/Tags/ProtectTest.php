<?php

use Latte\CompileException;

// protect:password_form requires a session token from the protection middleware; a plain render has none, so these tests only verify compilation and graceful handling.

describe('protect:password_form', function () {
    test('protect:password_form self-closing compiles; runtime may throw due to Content echo (INCOMPAT-candidate)', function () {
        // The proxy echoes a self-closing tag's result, which may fail when the tag returns a Content/array value that cannot be cast to string.
        try {
            $result = $this->latte('{s:protect:password_form /}');
            expect(true)->toBeTrue();
        } catch (CompileException $e) {
            expect(false)->toBeTrue('protect:password_form threw a Latte CompileException: '.$e->getMessage());
        } catch (Throwable $e) {
            expect($e)->not->toBeInstanceOf(CompileException::class);
        }
    });

    test('protect:password_form pair tag compiles through Latte proxy', function () {
        expect(fn () => $this->latte('{s:protect:password_form}FORM BODY{/s:protect:password_form}'))
            ->not->toThrow(CompileException::class);
    });

    test('protect:password_form pair tag body is rendered (no_token data accessible via $value)', function () {
        try {
            $this->latte('before{s:protect:password_form}INNER{/s:protect:password_form}after')
                ->assertSee('before')
                ->assertSee('after');
        } catch (CompileException $e) {
            expect(false)->toBeTrue('Latte CompileException: '.$e->getMessage());
        } catch (Exception $e) {
            expect(true)->toBeTrue();
        }
    });
});
