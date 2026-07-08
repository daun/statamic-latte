<?php

use Latte\CompileException;

/**
 * Slots for Latte template components. A `<x-…>` whose `components/<name>.latte`
 * template exists is desugared to a native `{embed}`: `<x-slot>` children fill
 * blocks, the loose body fills the `default` block, and omitted slots fall back
 * to the template's own `{slot …}` content.
 *
 * Fixtures:
 *   components/alert.latte  <div class="{$class ?? 'alert'}">{slot header}default-header{/slot}|{slot default}default-body{/slot}</div>
 *   components/scope.latte  exposes $class, whether $secret leaked, and both slots
 *   components/panel.latte  <div>{$type}:{$heading}</div>  (backed by Tests\Components\Panel)
 */
beforeEach(function () {
    config(['latte.components_namespace' => 'Tests\\Components']);
});

describe('template component slots', function () {
    test('fills a named slot and the default slot', function () {
        $this->latte('<x-alert><x-slot name="header">Head</x-slot>Body</x-alert>')
            ->assertSee('<div class="alert">Head|Body</div>', false)
            ->assertDontSee('default-header', false)
            ->assertDontSee('default-body', false);
    });

    test('uses the template fallbacks when slots are omitted', function () {
        $this->latte('<x-alert/>')
            ->assertSee('<div class="alert">default-header|default-body</div>', false);
    });

    test('a partial fill keeps the default of the unfilled slot', function () {
        $this->latte('<x-alert><x-slot name="header">Only</x-slot></x-alert>')
            ->assertSee('<div class="alert">Only|default-body</div>', false);
    });

    test('supports the <x-slot:name> colon syntax', function () {
        $this->latte('<x-alert><x-slot:header>Colon</x-slot:header>Body</x-alert>')
            ->assertSee('<div class="alert">Colon|Body</div>', false);
    });

    test('a whitespace-only body still falls back to the default slot', function () {
        $this->latte('<x-alert>   </x-alert>', squish: false)
            ->assertSee('default-body', false);
    });

    test('passes an attribute through to the template', function () {
        $this->latte('<x-alert class="large">Body</x-alert>')
            ->assertSee('<div class="large">default-header|Body</div>', false);
    });

    test('errors when a named slot has no name', function () {
        expect(fn () => $this->latte('<x-alert><x-slot>Nameless</x-slot></x-alert>'))
            ->toThrow(CompileException::class, 'needs a name');
    });
});

describe('slot scope', function () {
    test('slot content reads the caller scope; the component body is isolated', function () {
        $this->latte(
            '<x-scope class="c1"><x-slot name="header">{$outer}</x-slot>body</x-scope>',
            ['outer' => 'OUTER-VAR', 'secret' => 'SECRET'],
        )
            // the slot fill sees $outer without it being passed as an attribute
            ->assertSee('OUTER-VAR', false)
            // the attribute reaches the isolated component body
            ->assertSee('class=c1', false)
            // the component body cannot see the caller's un-passed $secret
            ->assertSee('leak=NO-LEAK', false)
            ->assertDontSee('SECRET', false);
    });
});

describe('backing class', function () {
    test('spreads the class data() over the attributes', function () {
        $this->latte('<x-panel type="warn"/>')
            ->assertSee('<div>warn:WARN</div>', false);
    });

    test('applies constructor defaults when the attribute is omitted', function () {
        $this->latte('<x-panel/>')
            ->assertSee('<div>info:INFO</div>', false);
    });
});

describe('coexistence', function () {
    test('a real {embed} and a template component share no block layer', function () {
        $this->latte(
            "{embed file 'embed-box'}{slot title}BoxTitle{/slot}{/embed}"
                .'<x-alert><x-slot name="header">Head</x-slot>Body</x-alert>',
        )
            ->assertSee('BoxTitle', false)
            ->assertSee('<div class="alert">Head|Body</div>', false)
            ->assertDontSee('default-title', false)
            ->assertDontSee('default-header', false)
            ->assertDontSee('default-body', false);
    });

    test('template components nest', function () {
        $this->latte('<x-alert><x-slot name="header"><x-badge label="In"/></x-slot>Body</x-alert>')
            ->assertSee('<div class="alert"><span class="badge">In</span>|Body</div>', false);
    });
});
