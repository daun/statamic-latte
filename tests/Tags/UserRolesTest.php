<?php

// CLASSIFY: OK — user role fixture (editor) exists; tests assert real role data

describe('user_roles', function () {
    test('renders role title when iterating all roles', function () {
        $this->latte('{s:user_roles}{$value->title}{/s:user_roles}')
            ->assertSee('Editor');
    });

    test('renders role handle when iterating', function () {
        $this->latte('{s:user_roles}{$value->handle}{/s:user_roles}')
            ->assertSee('editor');
    });

    test('supports as: param capturing role list', function () {
        $this->latte('{s:user_roles as: roles}{foreach $roles as $r}{$r->handle}{/foreach}{/s:user_roles}')
            ->assertSee('editor');
    });

    test('renders surrounding static content', function () {
        $this->latte('start {s:user_roles}{$value->handle}{/s:user_roles} end')
            ->assertSee('start')
            ->assertSee('editor')
            ->assertSee('end');
    });

    test('exposes value handle and title fields together', function () {
        $this->latte('{s:user_roles}{$value->handle}:{$value->title}{/s:user_roles}')
            ->assertSee('editor:Editor');
    });
});
