<?php

use Latte\Engine;

describe('modifiers', function () {
    test('defines statamic modifiers as filters', function () {
        $this->latte('I like {$things|sentence_list}', ['things' => ['a', 'b', 'c']])
            ->assertSee('I like a, b, and c');

        $this->latte('{if ("ABC"|is_uppercase)} YES {else} NO {/if}')
            ->assertSee('YES');

        $this->latte('{("Just Because I Can"|dashify)}')
            ->assertSee('just-because-i-can');
    });

    test('preserves existing filters', function () {
        $latte = $this->app->get(Engine::class);
        $latte->addFilter('dashify', fn ($str) => $str);

        $this->latte('{("Just Because I Can"|dashify)}')
            ->assertSee('Just Because I Can')
            ->assertDontSee('just-because-i-can');
    });
});
