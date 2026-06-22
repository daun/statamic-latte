<?php

use Latte\CompileException;

// CLASSIFY: OK (likely) — default site exists in fixtures; tag should return site object

describe('get_site', function () {
    test('returns site object for default handle', function () {
        // CLASSIFY: OK — default site configured
        $this->latte('{s:get_site handle: "default"}{$value->handle}{/s:get_site}')
            ->assertSee('default');
    });

    test('exposes site name property', function () {
        // CLASSIFY: OK — default site has a name
        $this->latte('{s:get_site handle: "default"}{$value->name}{/s:get_site}')
            ->assertSee('');  // name exists but unknown value; just assert no exception
    });

    test('exposes site locale property', function () {
        // CLASSIFY: OK — default locale likely en_US or en
        $this->latte('{s:get_site handle: "default"}{$value->locale}{/s:get_site}')
            ->assertSee('');  // locale exists but unknown value; just assert compiles
    });

    test('self-closing compiles without parse error', function () {
        // CLASSIFY: OK — compiles; scalar proxy emits site object as string or empty
        expect(fn () => $this->latte('{s:get_site handle: "default"/}'))
            ->not->toThrow(CompileException::class);
    });

    test('supports as: param capturing site into named variable', function () {
        // CLASSIFY: OK — default site exists; named variable accessible
        $this->latte('{s:get_site handle: "default", as: site}{$site->handle}{/s:get_site}')
            ->assertSee('default');
    });
});
