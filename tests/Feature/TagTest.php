<?php

describe('s:tag', function () {
    test('renders s:link tag pair', function () {
        $this->latte('A link to {s:link to: "fanny-packs"}{$result}{/s:link}')->assertSee('A link to /fanny-packs');
    });

    test('renders empty s:link tag pair', function () {
        $this->latte('Go to {s:link to: "fanny-packs"}{/s:link} link')->assertSee('Go to  link');
    });

    test('renders self-closing s:link tag', function () {
        $this->latte('Another {s:link to: "fanny-packs"/} link')->assertSee('Another  link');
    });

    // Not supported in Latte 3+ — see https://github.com/nette/latte/issues/382
    // test('renders s:link single tag', function () {
    //     $this->latte('Another {s:link to: "fanny-packs"} link')->assertSee('Another  link');
    // });
})->only();
