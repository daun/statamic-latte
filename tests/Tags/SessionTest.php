<?php

// CLASSIFY per test — session tags require a booted session (app.key + GET request).
// session:set / session:flash / session:forget / session:flush are side-effecting.
// session:get and session:has work once the session driver is started.

beforeEach(function () {
    config(['app.key' => 'base64:mLPJYVnk066Xex1MasJvUXpJThbL8Jin1IDSbZ6n/Ns=']);
    $this->get('/');
});

describe('session:has', function () {
    test('returns false for a key that is not in session', function () {
        // CLASSIFY: OK — session booted; has() returns false for missing key
        $this->latte('{if s("session:has", ["key" => "ghost"])}YES{/if}')
            ->assertDontSee('YES');
    });

    test('self-closing session:has echoes empty for missing key', function () {
        // CLASSIFY: OK — returns false → echo empty string
        $this->latte('x{s:session:has key: "ghost" /}y')
            ->assertSee('xy');
    });
});

describe('session:value / session wildcard', function () {
    test('session:value returns empty for missing key', function () {
        // CLASSIFY: FIXTURE — session has no "mykey" set; returns null
        $this->latte('{s:session:value key: "mykey" /}')
            ->assertSee('');
    });

    test('session:value returns default when key absent', function () {
        // CLASSIFY: OK — default param returned
        $this->latte('{s:session:value key: "nope", default: "fallback" /}')
            ->assertSee('fallback');
    });
});

describe('session:set', function () {
    test('session:set self-closing produces no output', function () {
        // CLASSIFY: OK — set() not a pair; returnableSession returns null
        $this->latte('before{s:session:set foo: "bar" /}after')
            ->assertSee('beforeafter');
    });
});

describe('session:flash', function () {
    test('session:flash self-closing produces no output', function () {
        // CLASSIFY: OK — flash() not a pair; no output
        $this->latte('before{s:session:flash notice: "hello" /}after')
            ->assertSee('beforeafter');
    });
});

describe('session:forget', function () {
    test('session:forget self-closing produces no output', function () {
        // CLASSIFY: OK — forget() not a pair; no output
        $this->latte('before{s:session:forget keys: "foo" /}after')
            ->assertSee('beforeafter');
    });
});

describe('session:flush', function () {
    test('session:flush self-closing produces no output', function () {
        // CLASSIFY: OK — flush() returns null; no echo
        $this->latte('before{s:session:flush /}after')
            ->assertSee('beforeafter');
    });
});

describe('session pair tag', function () {
    test('pair session tag compiles and renders surrounding text with no session data', function () {
        // CLASSIFY: FIXTURE — no session data; tag returns null/empty array
        $this->latte('start{s:session}{/s:session}end')
            ->assertSee('start')
            ->assertSee('end');
    });
});
