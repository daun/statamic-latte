<?php

describe('loader', function () {
    test('resolves files from the root', function () {
        $this->view('loader.include')->assertSee('Welcome to Laravel Latte');
    });

    test('resolves files from subfolders', function () {
        $this->view('loader.nested.include')->assertSee('Welcome to Laravel Latte');
    });

    test('resolves relative paths', function () {
        $this->view('loader.relative')->assertSee('Welcome to Laravel Latte');
        $this->view('loader.relative-extension')->assertSee('Welcome to Laravel Latte');
    });
});
