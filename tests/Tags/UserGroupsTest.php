<?php

// CLASSIFY: OK — user group fixture (editors) exists; tests assert real group data

describe('user_groups', function () {
    test('renders group title when iterating all groups', function () {
        $this->latte('{s:user_groups}{$value->title}{/s:user_groups}')
            ->assertSee('Editors');
    });

    test('renders group handle when iterating', function () {
        $this->latte('{s:user_groups}{$value->handle}{/s:user_groups}')
            ->assertSee('editors');
    });

    test('supports as: param capturing group list', function () {
        $this->latte('{s:user_groups as: groups}{foreach $groups as $g}{$g->handle}{/foreach}{/s:user_groups}')
            ->assertSee('editors');
    });

    test('renders surrounding static content', function () {
        $this->latte('start {s:user_groups}{$value->handle}{/s:user_groups} end')
            ->assertSee('start')
            ->assertSee('editors')
            ->assertSee('end');
    });

    test('exposes value handle and title fields together', function () {
        $this->latte('{s:user_groups}{$value->handle}:{$value->title}{/s:user_groups}')
            ->assertSee('editors:Editors');
    });
});
