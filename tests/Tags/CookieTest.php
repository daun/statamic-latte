<?php

// An app key is required for Laravel's encrypted cookies.
beforeEach(function () {
    config(['app.key' => 'base64:mLPJYVnk066Xex1MasJvUXpJThbL8Jin1IDSbZ6n/Ns=']);
});

describe('cookie:value', function () {
    test('returns empty when cookie key does not exist', function () {
        $this->latte('{s:cookie:value key: "nonexistent" /}')
            ->assertSee('');
    });

    test('returns default when cookie missing and default provided', function () {
        $this->latte('{s:cookie:value key: "missing", default: "fallback" /}')
            ->assertSee('fallback');
    });

    test('pair form compiles and renders surrounding content', function () {
        $this->latte('before{s:cookie:value key: "x"}[{$value}]{/s:cookie:value}after')
            ->assertSee('before')
            ->assertSee('after');
    });
});

describe('cookie:has', function () {
    test('returns false when cookie does not exist', function () {
        $this->latte('{if s("cookie:has", ["key" => "absent"])}YES{/if}')
            ->assertDontSee('YES');
    });

    test('self-closing cookie:has echoes nothing for missing key', function () {
        $this->latte('x{s:cookie:has key: "absent" /}y')
            ->assertSee('xy');
    });
});

describe('cookie:set', function () {
    test('cookie:set self-closing does not output anything', function () {
        $this->latte('before{s:cookie:set mykey: "myvalue" /}after')
            ->assertSee('beforeafter');
    });
});

describe('cookie:forget', function () {
    test('cookie:forget self-closing does not output anything', function () {
        $this->latte('before{s:cookie:forget keys: "somekey" /}after')
            ->assertSee('beforeafter');
    });
});
