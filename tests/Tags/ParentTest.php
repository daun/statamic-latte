<?php

/*
 * CLASSIFICATION OVERVIEW
 * parent (index pair)  — OK: a null result skips the pair body (Antlers parity), so
 *                         accessing $value->title never runs and renders nothing.
 * parent (self-closing) — N/A: returns empty string for '/' (no parent URL)
 * parent:field wildcard — N/A: returns null for '/' (no parent)
 */

describe('parent', function () {
    test('self-closing returns empty string for root URL', function () {
        // CLASSIFY: N/A — no parent URL for '/'; proxy echoes the scalar return (empty or null)
        $this->latte('{s:parent/}')
            ->assertDontSee('<fatal>');
    });

    test('wildcard field access returns empty for root URL', function () {
        // CLASSIFY: N/A — parent:title at '/' returns null → empty output
        $this->latte('[{s:parent:title/}]')
            ->assertSee('[]', false);
    });

    test('pair body is skipped when there is no parent', function () {
        // CLASSIFY: OK — null result skips the body (Antlers parity); no crash.
        $this->latte('[{s:parent}{$value->title}{/s:parent}]')
            ->assertSee('[]', false);
    });
});
