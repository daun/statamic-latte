<?php

// CLASSIFY: FIXTURE — no user roles configured; tests verify Latte compilation and graceful empty output

describe('user_roles', function () {
    test('compiles and renders empty when no roles exist', function () {
        // CLASSIFY: FIXTURE — no roles
        $this->latte('{s:user_roles}{$value->title}{/s:user_roles}')
            ->assertSee('');
    });

    test('supports as: param', function () {
        // CLASSIFY: FIXTURE — no roles
        $this->latte('{s:user_roles as: roles}{foreach $roles as $r}{$r->handle}{/foreach}{/s:user_roles}')
            ->assertSee('');
    });

    test('renders surrounding static content', function () {
        // CLASSIFY: FIXTURE — no roles
        $this->latte('start {s:user_roles}{$value->handle}{/s:user_roles} end')
            ->assertSee('start')
            ->assertSee('end');
    });

    test('exposes value fields in body', function () {
        // CLASSIFY: FIXTURE — no roles; body compiles even if never executed
        $this->latte('{s:user_roles}{$value->handle} {$value->title}{/s:user_roles}')
            ->assertSee('');
    });
});
