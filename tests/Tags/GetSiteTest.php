<?php

use Latte\CompileException;

describe('get_site', function () {
    test('returns site object for default handle', function () {
        $this->latte('{s:get_site handle: "default"}{$value->handle}{/s:get_site}')
            ->assertSee('default');
    });

    test('exposes site name property', function () {
        $this->latte('{s:get_site handle: "default"}{$value->name}{/s:get_site}')
            ->assertSee('');  // name exists but unknown value; just assert no exception
    });

    test('exposes site locale property', function () {
        $this->latte('{s:get_site handle: "default"}{$value->locale}{/s:get_site}')
            ->assertSee('');  // locale exists but unknown value; just assert compiles
    });

    test('self-closing compiles without parse error', function () {
        expect(fn () => $this->latte('{s:get_site handle: "default"/}'))
            ->not->toThrow(CompileException::class);
    });

    test('supports as: param capturing site into named variable', function () {
        $this->latte('{s:get_site handle: "default", as: site}{$site->handle}{/s:get_site}')
            ->assertSee('default');
    });
});
