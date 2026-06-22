<?php

use Latte\CompileException;

// Some Statamic tags are intentionally blocked at compile time because Latte has a
// native equivalent (or they are language/structure constructs). The proxy throws a
// CompileException steering the author to the idiomatic replacement.
// See TagNode::$unsupportedTags.

describe('unsupported tags', function () {
    test('loop is blocked in favour of {for}/{foreach}', function () {
        expect(fn () => $this->latte('{s:loop times: 3 /}'))
            ->toThrow(CompileException::class, 'Use the built-in `{for}` or `{foreach}` tag instead');
    });

    test('increment is blocked in favour of variable assigment', function () {
        expect(fn () => $this->latte('{s:increment /}'))
            ->toThrow(CompileException::class, 'variable assigment');
    });

    test('dump is blocked in favour of {dump}', function () {
        expect(fn () => $this->latte('{s:dump /}'))
            ->toThrow(CompileException::class, 'Use the built-in `{dump}` tag instead');
    });
});
