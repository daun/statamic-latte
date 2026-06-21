<?php

// CLASSIFY: OK — user fixtures exist; tests assert real data from alice@example.com and bob@example.com

describe('users', function () {
    test('renders all user names when iterating', function () {
        $this->latte('{s:users}{$value->name}{sep}, {/sep}{/s:users}')
            ->assertSee('Alice Smith')
            ->assertSee('Bob Jones');
    });

    test('sorts by email ascending by default', function () {
        // alice@ < bob@ alphabetically
        $this->latte('{s:users}{$value->email}{sep}, {/sep}{/s:users}')
            ->assertSeeInOrder(['alice@example.com', 'bob@example.com'], false);
    });

    test('supports as: param capturing result', function () {
        $this->latte('{s:users as: allUsers}{foreach $allUsers as $u}{$u->name}{sep}, {/sep}{/foreach}{/s:users}')
            ->assertSee('Alice Smith')
            ->assertSee('Bob Jones');
    });

    test('group filter narrows results to group members only', function () {
        // Alice is in editors; Bob is not
        $this->latte('{s:users group: editors}{$value->name}{/s:users}')
            ->assertSee('Alice Smith')
            ->assertDontSee('Bob Jones');
    });

    test('renders fallback static content alongside iteration', function () {
        $this->latte('before {s:users}{$value->email}{sep},{/sep}{/s:users} after')
            ->assertSee('before')
            ->assertSee('after')
            ->assertSee('alice@example.com');
    });

    test('s:users:count throws a friendly error for the unknown method', function () {
        // CLASSIFY: OK — UserTags has no count() method; the proxy rethrows with guidance.
        expect(fn () => $this->latte('{s:users:count /}'))
            ->toThrow(BadMethodCallException::class, "'count' is not a valid method of the users tag");
    });
});
