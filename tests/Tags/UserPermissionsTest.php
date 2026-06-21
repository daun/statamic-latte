<?php

// CLASSIFY: OK — boolean tags require an authenticated user; with none they return false.
// A false (or null) result now skips the pair body (Antlers parity), so the body only
// renders when the permission/role/group check passes. The s() helper also works in {if}.

describe('user:can', function () {
    test('self-closing user:can returns empty without authenticated user', function () {
        // CLASSIFY: N/A — no auth user; tag returns false → echoes empty string
        $this->latte('before{s:user:can permission: "super" /}after')
            ->assertSee('beforeafter');
    });

    test('pair user:can body is skipped when the check fails', function () {
        // CLASSIFY: OK — false result skips the body (Antlers parity).
        $this->latte('[{s:user:can permission: "super"}SECRET{/s:user:can}]')
            ->assertSee('[]', false)
            ->assertDontSee('SECRET');
    });

    test('s() helper user:can returns false without user — can be used in {if}', function () {
        // CLASSIFY: N/A — correct guard: s() returns false → {if} suppresses body
        $this->latte('{if s("user:can", ["permission" => "admin"])}YES{/if}')
            ->assertDontSee('YES');
    });
});

describe('user:is', function () {
    test('pair user:is body is skipped when the check fails', function () {
        // CLASSIFY: OK — false result skips the body.
        $this->latte('[{s:user:is role: "admin"}ADMIN{/s:user:is}]')
            ->assertSee('[]', false)
            ->assertDontSee('ADMIN');
    });

    test('s() helper user:is returns false without user', function () {
        // CLASSIFY: N/A — s() can be used as a safe boolean guard
        $this->latte('{if s("user:is", ["role" => "admin"])}ADMIN{/if}')
            ->assertDontSee('ADMIN');
    });
});

describe('user:in', function () {
    test('pair user:in body is skipped when the check fails', function () {
        // CLASSIFY: OK — false result skips the body.
        $this->latte('[{s:user:in group: "editors"}EDITOR{/s:user:in}]')
            ->assertSee('[]', false)
            ->assertDontSee('EDITOR');
    });

    test('s() helper user:in returns false without user', function () {
        // CLASSIFY: N/A — s() can be used as a safe boolean guard
        $this->latte('{if s("user:in", ["group" => "editors"])}EDITOR{/if}')
            ->assertDontSee('EDITOR');
    });
});
