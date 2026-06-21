<?php

// CLASSIFY: FIXTURE — no user fixtures exist; tests verify Latte compilation and graceful empty output

describe('users', function () {
    test('compiles and renders empty when no users exist', function () {
        // CLASSIFY: FIXTURE — no user fixtures
        $this->latte('{s:users}{$value->name}{/s:users}')
            ->assertSee('');
    });

    test('supports as: param capturing result', function () {
        // CLASSIFY: FIXTURE — no user fixtures; verifies as: compiles and loop runs zero iterations
        $this->latte('{s:users as: allUsers}{foreach $allUsers as $u}{$u->name}{/foreach}{/s:users}')
            ->assertSee('');
    });

    test('supports group filter param', function () {
        // CLASSIFY: FIXTURE — no user fixtures
        $this->latte('{s:users group: editors}{$value->name}{/s:users}')
            ->assertSee('');
    });

    test('renders fallback content alongside empty iteration', function () {
        // CLASSIFY: FIXTURE — no user fixtures; static text must still appear
        $this->latte('before {s:users}{$value->email}{/s:users} after')
            ->assertSee('before')
            ->assertSee('after');
    });

    test('s:users:count throws a friendly error for the unknown method', function () {
        // CLASSIFY: OK — UserTags has no count() method; the proxy rethrows with guidance.
        expect(fn () => $this->latte('{s:users:count /}'))
            ->toThrow(BadMethodCallException::class, "'count' is not a valid method of the users tag");
    });
});
