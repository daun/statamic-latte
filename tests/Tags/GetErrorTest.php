<?php

use Illuminate\Support\ViewErrorBag;

// Laravel's ShareErrorsFromSession middleware normally shares the `errors` bag; in tests we share it manually.
beforeEach(function () {
    view()->share('errors', new ViewErrorBag);
});

describe('get_error', function () {
    test('get_error self-closing renders nothing when no errors exist', function () {
        $this->latte('before{s:get_error /}after')
            ->assertSee('beforeafter');
    });

    test('get_error:field self-closing renders nothing when field has no error', function () {
        $this->latte('before{s:get_error:email /}after')
            ->assertSee('beforeafter');
    });

    test('get_error pair tag compiles and body is accessible', function () {
        $this->latte('start{s:get_error:email}{$value}{/s:get_error:email}end')
            ->assertSee('start')
            ->assertSee('end');
    });
});

describe('get_errors', function () {
    test('get_errors self-closing renders nothing when no errors exist', function () {
        $this->latte('before{s:get_errors /}after')
            ->assertSee('beforeafter');
    });

    test('get_errors pair tag compiles with body accessing value fields', function () {
        $this->latte('start{s:get_errors}{$value}{/s:get_errors}end')
            ->assertSee('start')
            ->assertSee('end');
    });

    test('get_errors:all self-closing renders nothing when no errors', function () {
        $this->latte('{s:get_errors:all /}')
            ->assertSee('');
    });
});
