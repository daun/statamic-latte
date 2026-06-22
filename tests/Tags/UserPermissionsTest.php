<?php

use Statamic\Facades\User;

// Alice (alice@example.com) has role "editor" with permission "access cp", is in group "editors".
// Bob (bob@example.com) has no roles or groups.

describe('user:can', function () {
    test('self-closing user:can returns empty without authenticated user', function () {
        // No auth user → tag returns false → stringified as empty string
        $this->latte('before{s:user:can permission: "super" /}after')
            ->assertSee('beforeafter');
    });

    test('pair user:can body is skipped when no user is authenticated', function () {
        $this->latte('[{s:user:can permission: "super"}SECRET{/s:user:can}]')
            ->assertSee('[]', false)
            ->assertDontSee('SECRET');
    });

    test('pair user:can body renders when authenticated user has the permission', function () {
        $alice = User::findByEmail('alice@example.com');
        $this->actingAs($alice);

        $this->latte('[{s:user:can permission: "access cp"}ALLOWED{/s:user:can}]')
            ->assertSee('[ALLOWED]', false)
            ->assertDontSee('[]');
    });

    test('pair user:can body is skipped when authenticated user lacks the permission', function () {
        $bob = User::findByEmail('bob@example.com');
        $this->actingAs($bob);

        $this->latte('[{s:user:can permission: "access cp"}ALLOWED{/s:user:can}]')
            ->assertSee('[]', false)
            ->assertDontSee('ALLOWED');
    });

    test('s() helper user:can returns false without user — can be used in {if}', function () {
        $this->latte('{if s("user:can", ["permission" => "admin"])}YES{/if}')
            ->assertDontSee('YES');
    });

    test('s() helper user:can returns true in {if} when user has permission', function () {
        $alice = User::findByEmail('alice@example.com');
        $this->actingAs($alice);

        $this->latte('{if s("user:can", ["permission" => "access cp"])}YES{/if}')
            ->assertSee('YES');
    });
});

describe('user:is', function () {
    test('pair user:is body is skipped when no user is authenticated', function () {
        $this->latte('[{s:user:is role: "editor"}ADMIN{/s:user:is}]')
            ->assertSee('[]', false)
            ->assertDontSee('ADMIN');
    });

    test('pair user:is body renders when authenticated user has the role', function () {
        $alice = User::findByEmail('alice@example.com');
        $this->actingAs($alice);

        $this->latte('[{s:user:is role: "editor"}EDITOR{/s:user:is}]')
            ->assertSee('[EDITOR]', false);
    });

    test('pair user:is body is skipped when authenticated user lacks the role', function () {
        $bob = User::findByEmail('bob@example.com');
        $this->actingAs($bob);

        $this->latte('[{s:user:is role: "editor"}EDITOR{/s:user:is}]')
            ->assertSee('[]', false)
            ->assertDontSee('EDITOR');
    });

    test('s() helper user:is returns false without user', function () {
        $this->latte('{if s("user:is", ["role" => "admin"])}ADMIN{/if}')
            ->assertDontSee('ADMIN');
    });
});

describe('user:in', function () {
    test('pair user:in body is skipped when no user is authenticated', function () {
        $this->latte('[{s:user:in group: "editors"}EDITOR{/s:user:in}]')
            ->assertSee('[]', false)
            ->assertDontSee('EDITOR');
    });

    test('pair user:in body renders when authenticated user is in the group', function () {
        $alice = User::findByEmail('alice@example.com');
        $this->actingAs($alice);

        $this->latte('[{s:user:in group: "editors"}IN_GROUP{/s:user:in}]')
            ->assertSee('[IN_GROUP]', false);
    });

    test('pair user:in body is skipped when authenticated user is not in the group', function () {
        $bob = User::findByEmail('bob@example.com');
        $this->actingAs($bob);

        $this->latte('[{s:user:in group: "editors"}IN_GROUP{/s:user:in}]')
            ->assertSee('[]', false)
            ->assertDontSee('IN_GROUP');
    });

    test('s() helper user:in returns false without user', function () {
        $this->latte('{if s("user:in", ["group" => "editors"])}EDITOR{/if}')
            ->assertDontSee('EDITOR');
    });
});
