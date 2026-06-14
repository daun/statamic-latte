<?php

describe('scalar tags', function () {
    test('passes value variable into tag pair context', function () {
        $this->latte('A link to {s:link to: "snacks"}{$value}{/s:link}')->assertSee('A link to /snacks');
    });

    test('renders empty tag pair using value variable', function () {
        $this->latte('Go to {s:link to: "snacks"}{/s:link} link')->assertSee('Go to /snacks link');
    });

    test('renders self-closing s:link tag using value variable', function () {
        $this->latte('Another {s:link to: "snacks"/} link')->assertSee('Another /snacks link');
    });

    // Not supported in Latte 3+ — see https://github.com/nette/latte/issues/382
    // test('renders s:link single tag', function () {
    //     $this->latte('Another {s:link to: "snacks"} link')->assertSee('Another /snacks link');
    // });
});

describe('iterable tags', function () {
    test('renders iterable statamic tags using foreach loop', function () {
        $this->latte(<<<'LATTE'
            {s:collection from: pages, order: title}
                {$value->title}{sep}, {/sep}
            {/s:collection}
        LATTE)
            ->assertSee('Testable, Testable With Layout');
    });

    test('saves result into local variable using `as` param', function () {
        $this->latte(<<<'LATTE'
            {s:collection as: entries, from: pages, order: title}
                {foreach $entries as $entry}
                    {$entry->title}{sep}, {/sep}
                {/foreach}
            {/s:collection}
        LATTE)
            ->assertSee('Testable, Testable With Layout');
    });
});
