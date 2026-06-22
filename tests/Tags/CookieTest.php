<?php

// CLASSIFY per test — cookie reads return empty when no cookies are set;
// cookie:set / cookie:forget are side-effecting and return no output (not a pair).
// App key required for encrypted cookies.

beforeEach(function () {
    config(['app.key' => 'base64:mLPJYVnk066Xex1MasJvUXpJThbL8Jin1IDSbZ6n/Ns=']);
});

describe('cookie:value', function () {
    test('returns empty when cookie key does not exist', function () {
        // CLASSIFY: FIXTURE — no cookie preset; returns null → echoes empty string
        $this->latte('{s:cookie:value key: "nonexistent" /}')
            ->assertSee('');
    });

    test('returns default when cookie missing and default provided', function () {
        // CLASSIFY: OK — default param returned when key absent
        $this->latte('{s:cookie:value key: "missing", default: "fallback" /}')
            ->assertSee('fallback');
    });

    test('pair form compiles and renders surrounding content', function () {
        // CLASSIFY: FIXTURE — no cookies; tag returns null; body sees $value = null
        $this->latte('before{s:cookie:value key: "x"}[{$value}]{/s:cookie:value}after')
            ->assertSee('before')
            ->assertSee('after');
    });
});

describe('cookie:has', function () {
    test('returns false when cookie does not exist', function () {
        // CLASSIFY: OK — Cookie::has returns false for missing key
        $this->latte('{if s("cookie:has", ["key" => "absent"])}YES{/if}')
            ->assertDontSee('YES');
    });

    test('self-closing cookie:has echoes nothing for missing key', function () {
        // CLASSIFY: OK — returns false (scalar) → echoes empty string
        $this->latte('x{s:cookie:has key: "absent" /}y')
            ->assertSee('xy');
    });
});

describe('cookie:set', function () {
    test('cookie:set self-closing does not output anything', function () {
        // CLASSIFY: OK — set() is not a pair tag; side-effect only; returnableCookie returns null
        $this->latte('before{s:cookie:set mykey: "myvalue" /}after')
            ->assertSee('beforeafter');
    });
});

describe('cookie:forget', function () {
    test('cookie:forget self-closing does not output anything', function () {
        // CLASSIFY: OK — forget() queues a cookie expiry; no output
        $this->latte('before{s:cookie:forget keys: "somekey" /}after')
            ->assertSee('beforeafter');
    });
});
