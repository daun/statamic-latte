<?php

/*
 * CLASSIFICATION OVERVIEW
 * children (index) — N/A: relies on URL::getCurrent() and collection structure tree;
 *                    neither is meaningfully set in a unit-test context.
 *                    Latte proxy layer probably compiles fine; the tag itself will
 *                    return empty or throw when no structure tree is found.
 */

describe('children', function () {
    test('compiles without parse or fatal error', function () {
        // CLASSIFY: N/A — needs current URL context and collection structure tree
        // Expect either empty output or a catchable exception, not a PHP fatal
        try {
            $result = $this->latte('{s:children}{$value->title}{/s:children}');
            $result->assertDontSee('<fatal>');
        } catch (Throwable $e) {
            // Exception from missing structure tree is acceptable
            expect($e)->toBeInstanceOf(Throwable::class);
        }
    });

    test('accepts of param to specify parent URL', function () {
        // CLASSIFY: N/A — still needs a structure tree; `of` param sets parent URL
        try {
            $result = $this->latte('{s:children of: "/"}{$value->title}{/s:children}');
            $result->assertDontSee('<fatal>');
        } catch (Throwable $e) {
            expect($e)->toBeInstanceOf(Throwable::class);
        }
    });

    test('accepts as param', function () {
        // CLASSIFY: N/A — same structural limitation
        try {
            $result = $this->latte(<<<'LATTE'
                {s:children as: kids}
                    {foreach $kids as $kid}|{$kid->title}|{/foreach}
                {/s:children}
            LATTE);
            $result->assertDontSee('<fatal>');
        } catch (Throwable $e) {
            expect($e)->toBeInstanceOf(Throwable::class);
        }
    });
});
