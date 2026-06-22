<?php

use Latte\CompileException;

describe('antlers', function () {
    test('renders inline antlers', function () {
        $this->latte('{$title} {antlers} {{ title }} {/antlers} {$title}', ['title' => 'Zero Sugar'])
            ->assertSee('Zero Sugar  Zero Sugar  Zero Sugar');
    });

    test('ignores latte inside an antlers tag', function () {
        $this->latte('{$title} {antlers} {$title} {{ title }} {/antlers} {$title}', ['title' => 'Zero Sugar'])
            ->assertSee('Zero Sugar  {$title} Zero Sugar  Zero Sugar');
    });

    test('throws when passing arguments to an antlers tag', function () {
        expect(fn () => $this->latte('{antlers arg: 123}{/antlers}'))
            ->toThrow(Exception::class, 'Unexpected arguments in {antlers}');
    });

    test('throws when using an n:antlers attribute', function () {
        expect(fn () => $this->latte('<div n:antlers></div>'))
            ->toThrow(CompileException::class, 'Attribute n:antlers is not supported');
    });
});
