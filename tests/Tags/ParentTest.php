<?php

describe('parent', function () {
    test('self-closing returns empty string for root URL', function () {
        $this->latte('{s:parent/}')
            ->assertDontSee('<fatal>');
    });

    test('wildcard field access returns empty for root URL', function () {
        $this->latte('[{s:parent:title/}]')
            ->assertSee('[]', false);
    });

    test('pair body is skipped when there is no parent', function () {
        // A null result skips the pair body (Antlers parity), so $value->title is never accessed.
        $this->latte('[{s:parent}{$value->title}{/s:parent}]')
            ->assertSee('[]', false);
    });
});
