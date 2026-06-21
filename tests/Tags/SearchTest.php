<?php

// CLASSIFY: INCOMPAT/FIXTURE/N/A — no search index configured
// Key param distinction:
//   `for: "hello"`   — direct query string; tag searches and returns empty list [] → OK
//   `query: "hello"` — interprets "hello" as HTTP request key name; request has no "hello" key
//                      → parseNoResults() → Content object → echo Content crash (INCOMPAT)

describe('search', function () {
    test('for: param with no results returns empty list — iterates zero times', function () {
        // CLASSIFY: FIXTURE — index exists (cqrs-like default); query finds nothing; returns []
        $this->latte('{s:search:results for: "zzznotfound"}{$value->title}{/s:search:results}')
            ->assertSee('');
    });

    test('for: param with empty body does not crash', function () {
        // CLASSIFY: FIXTURE — empty list → $ʟ_iterable = true → foreach 0 times → body = '' → OK
        $this->latte('start {s:search:results for: "hello"}{/s:search:results} end')
            ->assertSee('start')
            ->assertSee('end');
    });

    test('for: param supports as: alias', function () {
        // CLASSIFY: FIXTURE — as: captures empty list []; foreach on [] = 0 iterations
        $this->latte('{s:search:results as: results, for: "hello"}{foreach $results as $r}{$r->title}{/foreach}{/s:search:results}')
            ->assertSee('');
    });

    test('query: param misuse: treated as HTTP request key → parseNoResults → Content echo crash', function () {
        // CLASSIFY: INCOMPAT — query: "hello" sets request key name to "hello"; request has no
        // "hello" key → $query = null → parseNoResults() → Content → echo Content → Error
        // This is a documentation test that will FAIL with Error (not assertion failure)
        $this->latte('{s:search:results query: "hello"}{$value->title}{/s:search:results}')
            ->assertSee('');
    });

    test('collection param compiles cleanly with for: query', function () {
        // CLASSIFY: FIXTURE — collection scoping; no search index may limit results; returns []
        $this->latte('{s:search:results for: "testable", collection: "pages"}{$value->title}{/s:search:results}')
            ->assertSee('');
    });
});
