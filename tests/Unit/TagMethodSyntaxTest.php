<?php

use Daun\StatamicLatte\Latte\Support\TagMethodSyntax;

describe('TagMethodSyntax::rewrite', function () {
    test('rewrites a self-closing method tag', function () {
        expect(TagMethodSyntax::rewrite('{s:collection:count in: pages /}'))
            ->toBe('{s:collection __sl_tag: "collection:count", in: pages /}');
    });

    test('rewrites a self-closing method tag without args', function () {
        expect(TagMethodSyntax::rewrite('{s:nav:breadcrumbs /}'))
            ->toBe('{s:nav __sl_tag: "nav:breadcrumbs" /}');
    });

    test('rewrites a single method tag without args', function () {
        expect(TagMethodSyntax::rewrite('{s:collection:count}'))
            ->toBe('{s:collection __sl_tag: "collection:count"}');
    });

    test('rewrites paired method tags to base name', function () {
        expect(TagMethodSyntax::rewrite('{s:collection:count in: pages}{$value}{/s:collection:count}'))
            ->toBe('{s:collection __sl_tag: "collection:count", in: pages}{$value}{/s:collection}');
    });

    test('supports wildcard methods with no declared PHP method', function () {
        expect(TagMethodSyntax::rewrite('{s:nav:my_wildcard_segment /}'))
            ->toBe('{s:nav __sl_tag: "nav:my_wildcard_segment" /}');
    });

    test('leaves plain tags untouched', function () {
        foreach (['{s:collection in: pages /}', '{$value}', 'plain text', '{if $x}{/if}'] as $template) {
            expect(TagMethodSyntax::rewrite($template))->toBe($template);
        }
    });
});
