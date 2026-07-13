<?php

use Tests\Support\RegistersUserModifierTestCase;

uses(RegistersUserModifierTestCase::class);

test('user-defined modifiers override builtin latte filters', function () {
    // Latte's core `date` filter would format the timestamp; the user modifier
    // returns a fixed marker instead, proving it takes precedence.
    $this->latte('{$now|date}', ['now' => now()])
        ->assertSee('custom-date-modifier')
        ->assertDontSee(date('Y'));
});
