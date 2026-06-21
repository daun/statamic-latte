<?php

// CLASSIFY: FIXTURE
// Without page/entry context, locales:index() returns '' (scalar string) not an empty array.
// The proxy now skips the pair body for an empty-string result, so accessing
// $value->prop never runs and renders nothing instead of crashing.

describe('locales', function () {
    test('compiles and renders empty without page context (empty body)', function () {
        // CLASSIFY: FIXTURE — no page context; index() returns '' scalar; empty body is safe
        $this->latte('{s:locales}{/s:locales}')
            ->assertSee('');
    });

    test('renders surrounding static content with empty body', function () {
        // CLASSIFY: FIXTURE — empty body avoids $value->prop on scalar ''
        $this->latte('before {s:locales}{/s:locales} after')
            ->assertSee('before')
            ->assertSee('after');
    });

    test('s:locales:count compiles and renders zero without page context', function () {
        // CLASSIFY: FIXTURE — count() filters all locales when getData() returns null
        $this->latte('{s:locales:count /}')
            ->assertSee('0');
    });

    test('supports as: param to capture result without crashing', function () {
        // CLASSIFY: FIXTURE — as: captures '' (scalar); body iterates nothing
        $this->latte('{s:locales as: sites}{/s:locales}')
            ->assertSee('');
    });

    test('pair body is skipped for an empty-string result', function () {
        // CLASSIFY: OK — '' result skips the body; no ErrorException, renders nothing.
        $this->latte('[{s:locales}{$value->url}{/s:locales}]')
            ->assertSee('[]', false);
    });
});
