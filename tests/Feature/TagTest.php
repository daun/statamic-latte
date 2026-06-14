<?php

describe('s:tag', function () {
    test('renders s:link tag pair', function () {
        $this->latte('A link to {s:link to: "snacks"}{$result}{/s:link}')->assertSee('A link to /snacks');
    });

    test('renders empty s:link tag pair', function () {
        $this->latte('Go to {s:link to: "snacks"}{/s:link} link')->assertSee('Go to /snacks link');
    });

    test('renders self-closing s:link tag', function () {
        $this->latte('Another {s:link to: "snacks"/} link')->assertSee('Another /snacks link');
    });

    // Not supported in Latte 3+ — see https://github.com/nette/latte/issues/382
    // test('renders s:link single tag', function () {
    //     $this->latte('Another {s:link to: "snacks"} link')->assertSee('Another  link');
    // });
});
