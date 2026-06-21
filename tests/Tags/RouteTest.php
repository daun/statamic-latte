<?php

use Illuminate\Routing\Router;

// CLASSIFY: OK — named routes registered in beforeEach; tests assert real URL output

describe('route', function () {
    beforeEach(function () {
        /** @var Router $router */
        $router = app('router');
        $router->get('/hello', fn () => '')->name('hello');
        $router->get('/greet/{person}', fn () => '')->name('greet');
        $router->getRoutes()->refreshNameLookups();
    });

    test('self-closing returns URL for named route without params', function () {
        $this->latte('{s:route name: "hello"/}')
            ->assertSee('http://localhost/hello', false);
    });

    test('pair form exposes URL as $value', function () {
        $this->latte('{s:route name: "hello"}{$value}{/s:route}')
            ->assertSee('http://localhost/hello', false);
    });

    test('extra params are forwarded as route parameters', function () {
        $this->latte('{s:route name: "greet", person: "world"/}')
            ->assertSee('http://localhost/greet/world', false);
    });

    test('pair form works with parameterized route', function () {
        $this->latte('{s:route name: "greet", person: "alice"}{$value}{/s:route}')
            ->assertSee('http://localhost/greet/alice', false);
    });

    test('tag throws for unknown route name', function () {
        expect(fn () => $this->latte('{s:route name: "nonexistent"/}'))
            ->toThrow(Exception::class);
    });
});
