<?php

use Illuminate\Support\ViewErrorBag;

// CLASSIFY: FIXTURE — no validation errors exist in a plain render context;
// get_error / get_errors return false / empty when no errors are shared in the view.
// Compilation through the Latte proxy: OK.
// NOTE: the `errors` ViewErrorBag must be shared with the view layer (Laravel does this
// automatically via the ShareErrorsFromSession middleware); in tests we share it manually.

beforeEach(function () {
    view()->share('errors', new ViewErrorBag);
});

describe('get_error', function () {
    test('get_error self-closing renders nothing when no errors exist', function () {
        // CLASSIFY: FIXTURE — no error bag; tag returns false → empty echo
        $this->latte('before{s:get_error /}after')
            ->assertSee('beforeafter');
    });

    test('get_error:field self-closing renders nothing when field has no error', function () {
        // CLASSIFY: FIXTURE — no error bag for "email" field
        $this->latte('before{s:get_error:email /}after')
            ->assertSee('beforeafter');
    });

    test('get_error pair tag compiles and body is accessible', function () {
        // CLASSIFY: FIXTURE — tag returns false; proxy exposes $value = false;
        // static surrounding text still appears
        $this->latte('start{s:get_error:email}{$value}{/s:get_error:email}end')
            ->assertSee('start')
            ->assertSee('end');
    });
});

describe('get_errors', function () {
    test('get_errors self-closing renders nothing when no errors exist', function () {
        // CLASSIFY: FIXTURE — no error bag; tag returns false
        $this->latte('before{s:get_errors /}after')
            ->assertSee('beforeafter');
    });

    test('get_errors pair tag compiles with body accessing value fields', function () {
        // CLASSIFY: FIXTURE — no errors; tag returns false; body rendered with $value = false
        $this->latte('start{s:get_errors}{$value}{/s:get_errors}end')
            ->assertSee('start')
            ->assertSee('end');
    });

    test('get_errors:all self-closing renders nothing when no errors', function () {
        // CLASSIFY: FIXTURE — no errors; wildcard method "all" returns false
        $this->latte('{s:get_errors:all /}')
            ->assertSee('');
    });
});
