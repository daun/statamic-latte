<?php

use Daun\StatamicLatte\Data\Content;
use Illuminate\Support\Facades\Blade;
use Latte\CompileException;
use Tests\Components\Button;

/**
 * `<x-…>` components in Latte templates. One notation, decided at compile time:
 * a `components/<name>.latte` template is desugared to a native `{embed}`, and
 * anything else dispatches to a Laravel/Statamic Blade component at runtime.
 */
beforeEach(function () {
    // Backing Latte component classes resolve against this namespace.
    config(['latte.components_namespace' => 'Tests\\Components']);

    // Register the Blade class component under the `button` alias.
    Blade::component('button', Button::class);
});

describe('dispatch', function () {
    test('renders a Latte template component', function () {
        $this->latte('<x-badge label="New"/>')
            ->assertSee('<span class="badge">New</span>', false);
    });

    test('renders a Blade class component', function () {
        $this->latte('<x-button type="submit">Go</x-button>')
            ->assertSee('<button type="submit">Go</button>', false);
    });

    test('renders an anonymous Blade component', function () {
        $this->latte('<x-greeting name="World">there</x-greeting>')
            ->assertSee('Hi World: there', false);
    });

    test('a Latte template wins when a name resolves to both', function () {
        // Register a Blade alias under the same `badge` name; the Latte template
        // components/badge.latte must still win.
        Blade::component('badge', Button::class);

        $this->latte('<x-badge label="Win"/>')
            ->assertSee('<span class="badge">Win</span>', false)
            ->assertDontSee('<button', false);
    });
});

describe('subfolders', function () {
    test('resolves an anonymous Blade subfolder component via dot notation', function () {
        $this->latte('<x-forms.filter label="Tag">body</x-forms.filter>')
            ->assertSee('<div class="filter">Tag:body</div>', false);
    });

    test('resolves a Latte template subfolder component via dot notation', function () {
        // forms.field -> components/forms/field.latte (dot becomes a subfolder).
        $this->latte('<x-forms.field label="Hi"/>')
            ->assertSee('<span class="badge">Hi</span>', false);
    });
});

describe('slots', function () {
    test('decision 1.a: a paired Blade slot is echoed directly, not re-parsed', function () {
        // The rendered Latte slot output contains literal `{{ }}` and `@`
        // (emitted raw via |noescape). If the slot were re-parsed as Antlers it
        // would be mangled; the direct-echo path keeps it verbatim.
        $this->latte('<x-card>{$code|noescape}</x-card>', [
            'code' => 'Use {{ $var }} and @if in code',
        ])
            ->assertSee('<div class="card">Use {{ $var }} and @if in code</div>', false);
    });

    test('renders Latte expressions inside a Blade slot', function () {
        $this->latte('<x-card>Total: {1 + 2}</x-card>')
            ->assertSee('<div class="card">Total: 3</div>', false);
    });

    test('a self-closing Blade component has an empty slot', function () {
        $this->latte('<x-card/>')
            ->assertSee('<div class="card"></div>', false);
    });

    test('errors on a standalone <x-slot> outside a component', function () {
        expect(fn () => $this->latte('<x-slot:header>Hi</x-slot:header>'))
            ->toThrow(CompileException::class, 'direct child of a component');
    });
});

describe('named slots (Blade path)', function () {
    test('fills a named slot alongside the default slot', function () {
        $this->latte('<x-framed><x-slot:title>Head</x-slot:title>Body</x-framed>')
            ->assertSee('<div class="framed"><h1>Head</h1>Body</div>', false);
    });

    test('supports the <x-slot name="..."> syntax', function () {
        $this->latte('<x-framed><x-slot name="title">Head</x-slot>Body</x-framed>')
            ->assertSee('<div class="framed"><h1>Head</h1>Body</div>', false);
    });

    test('exposes slot attributes on the ComponentSlot', function () {
        $this->latte('<x-framed><x-slot:title class="big">Head</x-slot:title>Body</x-framed>')
            ->assertSee('class="big"', false)
            ->assertSee('>Head</h1>', false);
    });

    test('an omitted named slot is empty (isNotEmpty guard)', function () {
        $this->latte('<x-framed>JustBody</x-framed>')
            ->assertSee('<div class="framed">JustBody</div>', false)
            ->assertDontSee('<h1', false);
    });

    test('renders a nested component inside a named slot', function () {
        $this->latte('<x-framed><x-slot:title><x-badge label="In"/></x-slot:title>Body</x-framed>')
            ->assertSee('<span class="badge">In</span>', false)
            ->assertSee('Body</div>', false);
    });
});

describe('attributes', function () {
    test('a static attribute is a string', function () {
        $this->latte('<x-echo-type val="3"/>')
            ->assertSee('string:3', false);
    });

    test('a dynamic attribute keeps its PHP type', function () {
        $this->latte('<x-echo-type val={3}/>')
            ->assertSee('integer:3', false);
    });

    test('a dynamic attribute evaluates an expression', function () {
        $this->latte('<x-echo-type val={1 + 2}/>')
            ->assertSee('integer:3', false);
    });

    test('a dynamic attribute reads a template variable', function () {
        $this->latte('<x-echo-type val={$n}/>', ['n' => 42])
            ->assertSee('integer:42', false);
    });

    test('a bare attribute is a boolean true', function () {
        $this->latte('<x-echo-type val/>')
            ->assertSee('boolean:1', false);
    });

    test('extra attributes flow into the Blade $attributes bag', function () {
        $this->latte('<x-greeting name="X" class="big" data-id="7"/>')
            ->assertSee('class="big"', false)
            ->assertSee('data-id="7"', false);
    });
});

describe('spread', function () {
    test('spreads an array of attributes with ...={$array} (Blade path)', function () {
        $this->latte('<x-greeting ...={$opts}/>', [
            'opts' => ['name' => 'Ada', 'class' => 'big', 'data-id' => '7'],
        ])
            ->assertSee('Hi Ada:', false)
            ->assertSee('class="big"', false)
            ->assertSee('data-id="7"', false);
    });

    test('spreads with the brace-only ...{$array} form', function () {
        $this->latte('<x-greeting ...{$opts}/>', [
            'opts' => ['name' => 'Ada', 'class' => 'big'],
        ])
            ->assertSee('Hi Ada:', false)
            ->assertSee('class="big"', false);
    });

    test('the brace-only spread accepts any expression', function () {
        $this->latte('<x-greeting ...{$wrap[inner]}/>', [
            'wrap' => ['inner' => ['name' => 'Deep']],
        ])
            ->assertSee('Hi Deep:', false);
    });

    test('spreads into a Latte template component', function () {
        $this->latte('<x-badge ...={$opts}/>', ['opts' => ['label' => 'Spread']])
            ->assertSee('<span class="badge">Spread</span>', false);
    });

    test('a later explicit attribute overrides a spread entry', function () {
        $this->latte('<x-greeting ...={$opts} name="Override"/>', [
            'opts' => ['name' => 'Spread'],
        ])
            ->assertSee('Hi Override:', false);
    });

    test('a spread entry overrides an earlier explicit attribute', function () {
        $this->latte('<x-greeting name="First" ...={$opts}/>', [
            'opts' => ['name' => 'Spread'],
        ])
            ->assertSee('Hi Spread:', false);
    });

    test('errors on a bare ...$spread without braces', function () {
        expect(fn () => $this->latte('<x-greeting ...$opts/>', ['opts' => []]))
            ->toThrow(CompileException::class, 'use ...={$array} or ...{$array}');
    });

    test('errors on a filter applied to a brace-only spread', function () {
        expect(fn () => $this->latte('<x-greeting ...{$opts|upper}/>', ['opts' => []]))
            ->toThrow(CompileException::class, 'Filters are not supported on a spread');
    });
});

describe('value boundary', function () {
    test('Content wrappers are unwrapped on the Blade path', function () {
        $this->latte('<x-profile user={$user}/>', [
            'user' => new Content(['name' => 'Ada']),
        ])
            ->assertSee('<span>Ada</span>', false);
    });
});

describe('control attributes', function () {
    test('n:foreach over a component renders the right count with balanced state', function () {
        $before = ob_get_level();

        $this->latte(
            '<ul><x-greeting n:foreach="$names as $name" name={$name}/></ul>',
            ['names' => ['Ann', 'Bob', 'Cy']],
        )
            ->assertSeeInOrder(['Hi Ann:', 'Hi Bob:', 'Hi Cy:'], false);

        expect(ob_get_level())->toBe($before);
    });

    test('n:if removes a component when false', function () {
        $this->latte('<x-card n:if="$show">x</x-card>', ['show' => false])
            ->assertDontSee('card', false);

        $this->latte('<x-card n:if="$show">x</x-card>', ['show' => true])
            ->assertSee('<div class="card">x</div>', false);
    });

    test('nested components render', function () {
        $this->latte('<x-card><x-badge label="In"/></x-card>')
            ->assertSee('<div class="card"><span class="badge">In</span></div>', false);
    });
});
