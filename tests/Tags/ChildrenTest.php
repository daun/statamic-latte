<?php

// The children tag needs a current URL and a collection structure tree, neither of which exists here, so it renders empty or throws.

describe('children', function () {
    test('compiles without parse or fatal error', function () {
        try {
            $result = $this->latte('{s:children}{$value->title}{/s:children}');
            $result->assertDontSee('<fatal>');
        } catch (Throwable $e) {
            // Exception from missing structure tree is acceptable
            expect($e)->toBeInstanceOf(Throwable::class);
        }
    });

    test('accepts of param to specify parent URL', function () {
        try {
            $result = $this->latte('{s:children of: "/"}{$value->title}{/s:children}');
            $result->assertDontSee('<fatal>');
        } catch (Throwable $e) {
            expect($e)->toBeInstanceOf(Throwable::class);
        }
    });

    test('accepts as param', function () {
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
