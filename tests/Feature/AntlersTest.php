<?php

test('renders inline antlers', function () {
    $this->latte('{$title} {antlers} {{ title }} {/antlers} {$title}', ['title' => 'Zero Sugar'])
        ->assertSee('Zero Sugar  Zero Sugar  Zero Sugar');
});

test('does not render latte in antlers tag', function () {
    $this->latte('{$title} {antlers} {$title} {{ title }} {/antlers} {$title}', ['title' => 'Zero Sugar'])
        ->assertSee('Zero Sugar  {$title} Zero Sugar  Zero Sugar');
});

test('throws when passing arguments to antlers tag', function () {
    expect(fn () => $this->latte('{antlers arg: 123}{/antlers}'))
        ->toThrow(\Exception::class, 'Unexpected arguments in {antlers}');
});

test('throws when using n:antlers attribute', function () {
    expect(fn () => $this->latte('<div n:antlers></div>'))
        ->toThrow(\Latte\CompileException::class, 'Attribute n:antlers is not supported');
});
