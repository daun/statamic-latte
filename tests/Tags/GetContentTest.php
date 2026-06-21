<?php

/*
 * CLASSIFICATION OVERVIEW
 * get_content from: id   — OK: fetches entry by UUID; fixture entry "Testable" exists
 * get_content from: uri  — OK: fetches entry by URI; /testable should resolve
 * get_content from: pipe — OK: multiple IDs pipe-delimited
 * get_content as:        — OK: as param captures collection into variable
 */

describe('get_content', function () {
    test('fetches a single entry by id and exposes value', function () {
        // CLASSIFY: OK — Testable entry id exists in pages fixture
        $this->latte(
            '{s:get_content from: "78063fba-60b8-4fd5-9cc9-b6ef4ac336c1"}{$value->title}{/s:get_content}'
        )->assertSee('Testable');
    });

    test('fetches entry by uri', function () {
        // CLASSIFY: OK — collection route is {parent_uri}/{slug}; testable slug maps to /testable
        $this->latte(
            '{s:get_content from: "/testable"}{$value->title}{/s:get_content}'
        )->assertSee('Testable');
    });

    test('fetches multiple entries by pipe-delimited ids', function () {
        // CLASSIFY: OK — two known published entry ids
        $this->latte(
            '{s:get_content from: "78063fba-60b8-4fd5-9cc9-b6ef4ac336c1|0020c540-d4cd-11e5-a837-0800200c9a66"}{$value->title}{sep}, {/sep}{/s:get_content}'
        )
            ->assertSee('Testable')
            ->assertSee('Testable With Layout');
    });

    test('captures results via as param', function () {
        // CLASSIFY: OK — as: entries; then foreach over $entries
        $this->latte(<<<'LATTE'
            {s:get_content as: entries, from: "78063fba-60b8-4fd5-9cc9-b6ef4ac336c1"}
                {foreach $entries as $entry}|{$entry->title}|{/foreach}
            {/s:get_content}
        LATTE)
            ->assertSee('|Testable|');
    });

    test('returns empty for unknown id', function () {
        // CLASSIFY: OK — non-existent id; tag returns empty collection → no output
        $this->latte(
            '{s:get_content from: "00000000-0000-0000-0000-000000000000"}{$value->title}{/s:get_content}'
        )->assertDontSee('Testable');
    });
});
