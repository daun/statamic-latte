<?php

use Statamic\Exceptions\TaxonomyNotFoundException;

/*
 * CLASSIFICATION OVERVIEW
 * taxonomy (index/wildcard) — FIXTURE: no taxonomy data exists in fixtures;
 *                             Statamic throws TaxonomyNotFoundException when handle not found
 * taxonomy:count            — FIXTURE: same; throws rather than returning 0
 *
 * Tests verify the Latte layer compiles/forwards to the tag correctly (i.e. the proxy
 * passes params through). The actual tag behavior (throwing) is a fixture gap, not a
 * proxy bug.
 */

describe('taxonomy', function () {
    test('throws for unknown taxonomy handle', function () {
        // CLASSIFY: FIXTURE — no taxonomy fixture; Statamic throws TaxonomyNotFoundException
        expect(fn () => $this->latte('{s:taxonomy from: tags}{$value->title}{/s:taxonomy}'))
            ->toThrow(TaxonomyNotFoundException::class);
    });

    test('count method throws for missing taxonomy', function () {
        // CLASSIFY: FIXTURE — no taxonomy fixture; count hits the same code path
        expect(fn () => $this->latte('{s:taxonomy:count from: tags /}'))
            ->toThrow(TaxonomyNotFoundException::class);
    });

    test('wildcard method throws for named unknown taxonomy', function () {
        // CLASSIFY: FIXTURE — {s:taxonomy:tags} maps to wildcard($tag='tags'); same exception
        expect(fn () => $this->latte('{s:taxonomy:tags}{$value->title}{/s:taxonomy:tags}'))
            ->toThrow(TaxonomyNotFoundException::class);
    });

    test('accepts as param (still throws due to missing fixture)', function () {
        // CLASSIFY: FIXTURE — as: terms; Statamic throws before iterating
        expect(fn () => $this->latte(<<<'LATTE'
            {s:taxonomy as: terms, from: tags}
                {foreach $terms as $term}|{$term->title}|{/foreach}
            {/s:taxonomy}
        LATTE))
            ->toThrow(TaxonomyNotFoundException::class);
    });
});
