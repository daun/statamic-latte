<?php

use Illuminate\Support\Facades\Lang;

// CLASSIFICATION OVERVIEW
// trans: OK — key lookup, fallback lookup, and nested fallback expressions work
// through the proxy.

beforeEach(function () {
    config(['app.locale' => 'en']);

    Lang::addLines([
        'app.form_options.inline' => 'Inline label',
        'app.form_options.handle' => 'Handle label',
    ], 'en');
});

describe('trans', function () {
    test('renders a translated key', function () {
        // CLASSIFY: OK — scalar translation output renders from a self-closing tag
        $this->latte('{s:trans key: "app.form_options.inline" /}')
            ->assertSee('Inline label');
    });

    test('renders a translated fallback when the primary key is missing', function () {
        // CLASSIFY: OK — fallback is resolved as a second translation key
        $this->latte('{s:trans key: "app.form_options.missing", fallback: "app.form_options.handle" /}')
            ->assertSee('Handle label');
    });

    test('supports a nested trans expression as the fallback', function () {
        // CLASSIFY: OK — nested expression resolves before the outer fallback lookup
        $this->latte(<<<'LATTE'
            {var $inline_label = 'missing'}
            {var $handle = 'handle'}
            {s:trans key: "app.form_options.{$inline_label}", fallback: (s:trans key: "app.form_options.{$handle}") /}
            LATTE)
            ->assertSee('Handle label');
    });
});
