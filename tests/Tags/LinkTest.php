<?php

// CLASSIFY: OK — link tag is simple URL resolver; no assets/routes needed

describe('link', function () {
    test('renders URL in tag pair body via $value', function () {
        // CLASSIFY: OK
        $this->latte('{s:link to: "snacks"}{$value}{/s:link}')
            ->assertSee('/snacks');
    });

    test('self-closing renders URL inline', function () {
        // CLASSIFY: OK
        $this->latte('{s:link to: "/foo"/}')
            ->assertSee('/foo');
    });

    test('supports as: param capturing URL into named variable', function () {
        // CLASSIFY: OK
        $this->latte('{s:link to: "snacks", as: url}{$url}{/s:link}')
            ->assertSee('/snacks');
    });

    test('links to an entry by id', function () {
        // CLASSIFY: OK — `id:` resolves via getUrlFromId(); `to:`/`src:` are path-only by design.
        $this->latte('{s:link id: "78063fba-60b8-4fd5-9cc9-b6ef4ac336c1"}{$value}{/s:link}')
            ->assertSee('/testable')
            ->assertDontSee('78063fba');
    });

    test('renders surrounding static text unchanged', function () {
        // CLASSIFY: OK
        $this->latte('Go to {s:link to: "snacks"/} now')
            ->assertSee('Go to')
            ->assertSee('/snacks')
            ->assertSee('now');
    });
});
