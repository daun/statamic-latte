<?php

// CLASSIFY: FIXTURE — no user groups configured; tests verify Latte compilation and graceful empty output

describe('user_groups', function () {
    test('compiles and renders empty when no groups exist', function () {
        // CLASSIFY: FIXTURE — no groups
        $this->latte('{s:user_groups}{$value->title}{/s:user_groups}')
            ->assertSee('');
    });

    test('supports as: param', function () {
        // CLASSIFY: FIXTURE — no groups
        $this->latte('{s:user_groups as: groups}{foreach $groups as $g}{$g->handle}{/foreach}{/s:user_groups}')
            ->assertSee('');
    });

    test('renders surrounding static content', function () {
        // CLASSIFY: FIXTURE — no groups
        $this->latte('start {s:user_groups}{$value->handle}{/s:user_groups} end')
            ->assertSee('start')
            ->assertSee('end');
    });

    test('exposes value handle field', function () {
        // CLASSIFY: FIXTURE — no groups; body compiles even if never executed
        $this->latte('{s:user_groups}{$value->handle} {$value->title}{/s:user_groups}')
            ->assertSee('');
    });
});
