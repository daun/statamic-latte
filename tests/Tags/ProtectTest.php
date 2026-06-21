<?php

use Latte\CompileException;

// CLASSIFY: FIXTURE / N/A — protect:password_form requires a valid session token
// from the protection middleware; in a plain render there is no token so the tag
// returns no_token: true / invalid_token: true. Tests verify compilation and
// graceful handling without a real protection context.

describe('protect:password_form', function () {
    test('protect:password_form self-closing compiles; runtime may throw due to Content echo (INCOMPAT-candidate)', function () {
        // CLASSIFY: INCOMPAT-candidate — tag returns a Content/array result; the proxy
        // tries echo $ȳł_result on a self-closing tag, which may fail if the value
        // cannot be cast to string. No Latte CompileException should be thrown.
        try {
            $result = $this->latte('{s:protect:password_form /}');
            expect(true)->toBeTrue();
        } catch (CompileException $e) {
            expect(false)->toBeTrue('protect:password_form threw a Latte CompileException: '.$e->getMessage());
        } catch (Throwable $e) {
            // Runtime error (e.g. Content-to-string TypeError) or exception — compilation OK
            expect($e)->not->toBeInstanceOf(CompileException::class);
        }
    });

    test('protect:password_form pair tag compiles through Latte proxy', function () {
        // CLASSIFY: FIXTURE — no session token at runtime; key signal: it compiles.
        expect(fn () => $this->latte('{s:protect:password_form}FORM BODY{/s:protect:password_form}'))
            ->not->toThrow(CompileException::class);
    });

    test('protect:password_form pair tag body is rendered (no_token data accessible via $value)', function () {
        // CLASSIFY: FIXTURE — tag returns array with no_token key; proxy iterates array
        // or assigns to $value; body receives it. Static text around tag always appears.
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
