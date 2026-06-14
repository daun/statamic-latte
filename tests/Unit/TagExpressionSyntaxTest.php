<?php

use Daun\StatamicLatte\Latte\Support\TagExpressionSyntax;

function rewriteExpr(string $tpl): string
{
    return TagExpressionSyntax::rewrite($tpl);
}

test('rewrites a tag call with params', function () {
    expect(rewriteExpr('{var $c = (s:collection:count in: pages)}'))
        ->toBe("{var \$c = (s('collection:count', ['in' => 'pages']))}");
});

test('rewrites a tag call without params', function () {
    expect(rewriteExpr('{(s:nav:breadcrumbs)}'))
        ->toBe("{(s('nav:breadcrumbs'))}");
});

test('preserves surrounding expression context', function () {
    expect(rewriteExpr('{if (s:collection:count in: pages) > 1}x{/if}'))
        ->toBe("{if (s('collection:count', ['in' => 'pages'])) > 1}x{/if}");
});

test('leaves native paren expressions untouched', function () {
    expect(rewriteExpr('{var $x = (2 * 3)}'))->toBe('{var $x = (2 * 3)}');
});

test('rewrites any tag name (existence is a runtime concern)', function () {
    expect(rewriteExpr('(s:notatag here)'))->toBe("(s('notatag', ['here']))");
});

test('ignores a static-call lookalike', function () {
    expect(rewriteExpr('{(s::FOO)}'))->toBe('{(s::FOO)}');
});

test('handles nested parens inside params', function () {
    expect(rewriteExpr('{(s:link to: ("a"))}'))
        ->toBe("{(s('link', ['to' => 'a']))}");
});

test('compiles a parenthesised filter on a param value', function () {
    // Relies on Latte printing the filter as `($this->filters->...)` source,
    // which is valid in the recompiled template. This test exists to catch
    // that (intentional) accident breaking on a Latte upgrade.
    expect(rewriteExpr('{(s:collection:count in: ($c|lower))}'))
        ->toBe("{(s('collection:count', ['in' => (\$this->filters->lower)(\$c)]))}");
});

test('throws on a bare (unparenthesised) filter in a param', function () {
    rewriteExpr('{(s:collection:count in: $c|lower)}');
})->throws(\Latte\CompileException::class, 'Bare filters are not supported');

test('does not treat a logical or as a filter', function () {
    expect(rewriteExpr('{(s:collection from: ($a || $b))}'))
        ->toBe("{(s('collection', ['from' => \$a || \$b]))}");
});
