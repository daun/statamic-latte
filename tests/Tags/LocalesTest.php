<?php

// Without page context, locales:index() returns '' (a scalar string, not an empty array); the proxy skips the pair body for an empty-string result instead of crashing.

describe('locales', function () {
    test('compiles and renders empty without page context (empty body)', function () {
        $this->latte('{s:locales}{/s:locales}')
            ->assertSee('');
    });

    test('renders surrounding static content with empty body', function () {
        $this->latte('before {s:locales}{/s:locales} after')
            ->assertSee('before')
            ->assertSee('after');
    });

    test('s:locales:count compiles and renders zero without page context', function () {
        $this->latte('{s:locales:count /}')
            ->assertSee('0');
    });

    test('supports as: param to capture result without crashing', function () {
        $this->latte('{s:locales as: sites}{/s:locales}')
            ->assertSee('');
    });

    test('pair body is skipped for an empty-string result', function () {
        $this->latte('[{s:locales}{$value->url}{/s:locales}]')
            ->assertSee('[]', false);
    });
});
