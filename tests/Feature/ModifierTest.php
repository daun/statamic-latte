<?php

test('defines statamic modifiers', function () {
    $this->latte('I like {$things|sentence_list}', ['things' => ['a', 'b', 'c']])
        ->assertSee('I like a, b, and c');

    $this->latte('{if ("ABC"|is_uppercase)} YES {else} NO {/if}')
        ->assertSee('YES');
});
