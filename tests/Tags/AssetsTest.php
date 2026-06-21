<?php

// CLASSIFY: INCOMPAT
// Assets tag returns parseNoResults() (['no_results' => true, 'total_results' => 0]) when container not found.
// Normalizer converts assoc array to Content object. Proxy can't echo Content (no __toString).
// ALL bodies where body output is empty → echo Content → Error (incl. empty body).
// Only workaround: multi-property body with literal whitespace keeps $ʟ_body non-empty.

describe('assets', function () {
    test('any body where output is empty crashes: echo Content has no __toString', function () {
        // CLASSIFY: INCOMPAT — empty body → $ʟ_body = '' → proxy echoes Content → Error
        // This test documents the crash; will fail with Error (not assertion failure)
        $this->latte('start {s:assets container: "files"}{/s:assets} end')
            ->assertSee('start')
            ->assertSee('end');
    });

    test('body with literal whitespace between props avoids Content echo crash', function () {
        // CLASSIFY: INCOMPAT — spaces keep $ʟ_body non-empty; echo body (spaces) not Content
        $this->latte('{s:assets container: "files"}{$value->url} {$value->filename}{/s:assets}')
            ->assertSee('');
    });

    test('as: param with empty body avoids Content echo crash (aliased path)', function () {
        // CLASSIFY: INCOMPAT — as: path always echoes ob_get_clean() not Content; empty body = ''
        $this->latte('{s:assets as: files, container: "files"}{/s:assets}')
            ->assertSee('');
    });

    test('as: param exposes Content as $files; foreach iterates no_results and total_results keys', function () {
        // CLASSIFY: INCOMPAT — Content wraps ['no_results' => true, 'total_results' => 0];
        // foreach $files iterates both keys; $f is a scalar (true/0), not an asset
        $this->latte('{s:assets as: files, container: "files"}{foreach $files as $key => $f}{$key}={$f} {/foreach}{/s:assets}')
            ->assertSee('no_results=1')
            ->assertSee('total_results=0');
    });

    test('supports folder param syntax (compiles cleanly)', function () {
        // CLASSIFY: INCOMPAT — same echo Content crash as empty body; documents compile success
        $this->latte('{s:assets container: "files", folder: "images"}{$value->url} {$value->size}{/s:assets}')
            ->assertSee('');
    });
});
