<?php

/*
 * CLASSIFICATION OVERVIEW
 * taxonomy (index/wildcard) — OK: topics taxonomy + terms fixture in place
 * taxonomy:count            — OK: returns term count from topics
 */

describe('taxonomy', function () {
    test('renders terms from topics taxonomy', function () {
        $this->latte('{s:taxonomy from: topics}{$value->title}{sep}, {/sep}{/s:taxonomy}')
            ->assertSee('News')
            ->assertSee('Tutorials');
    });

    test('count returns correct number of terms', function () {
        $this->latte('{s:taxonomy:count from: topics /}')
            ->assertSee('2');
    });

    test('wildcard method renders terms by taxonomy handle', function () {
        $this->latte('{s:taxonomy:topics}{$value->title}{sep}, {/sep}{/s:taxonomy:topics}')
            ->assertSee('News')
            ->assertSee('Tutorials');
    });

    test('as param captures term list into named variable', function () {
        $this->latte(<<<'LATTE'
            {s:taxonomy as: terms, from: topics}
                {foreach $terms as $term}|{$term->title}|{/foreach}
            {/s:taxonomy}
        LATTE)
            ->assertSee('|News|')
            ->assertSee('|Tutorials|');
    });

    test('count tag method works as tag pair', function () {
        $this->latte('{s:taxonomy:count from: topics}{$value}{/s:taxonomy:count}')
            ->assertSee('2');
    });

    test('throws for unknown taxonomy handle', function () {
        expect(fn () => $this->latte('{s:taxonomy from: nonexistent}{$value->title}{/s:taxonomy}'))
            ->toThrow(\Statamic\Exceptions\TaxonomyNotFoundException::class);
    });
});
