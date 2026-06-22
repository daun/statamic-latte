<?php

use Latte\Engine;
use Latte\Loaders\StringLoader;

function renderLatte(array $templates, string $entry = 'main'): string
{
    $engine = app(Engine::class);
    $engine->setLoader(new StringLoader($templates));

    return $engine->renderToString($entry);
}

describe('slot', function () {
    test('fills a named slot of an embedded template', function () {
        $output = renderLatte([
            'figure' => '<figure><figcaption>{slot caption}{/slot}</figcaption></figure>',
            'main' => "{embed file 'figure'}{slot caption}Hello{/slot}{/embed}",
        ]);

        expect($output)->toContain('<figcaption>Hello</figcaption>');
    });

    test('defines a default that is used when the slot is not filled', function () {
        $output = renderLatte([
            'figure' => '<figure><figcaption>{slot caption}Default{/slot}</figcaption></figure>',
            'main' => "{embed file 'figure'}{/embed}",
        ]);

        expect($output)->toContain('<figcaption>Default</figcaption>');
    });

    test('behaves identically to {block} on both sides', function () {
        $withBlock = renderLatte([
            'figure' => '<figure><figcaption>{block caption}{/block}</figcaption></figure>',
            'main' => "{embed file 'figure'}{block caption}Hello{/block}{/embed}",
        ]);
        $withSlot = renderLatte([
            'figure' => '<figure><figcaption>{slot caption}{/slot}</figcaption></figure>',
            'main' => "{embed file 'figure'}{slot caption}Hello{/slot}{/embed}",
        ]);

        expect($withSlot)->toBe($withBlock);
    });

    test('works outside an embed as a plain block alias', function () {
        $output = renderLatte([
            'main' => '{slot greeting}Hi{/slot}',
        ]);

        expect($output)->toContain('Hi');
    });

    test('supports the n:slot attribute when filling from an embed', function () {
        $output = renderLatte([
            'figure' => '<figure><figcaption>{slot caption}{/slot}</figcaption></figure>',
            'main' => "{embed file 'figure'}<span n:slot=\"caption\">Hello</span>{/embed}",
        ]);

        expect(trim($output))->toBe('<figure><figcaption><span>Hello</span></figcaption></figure>');
    });

    test('supports the n:slot attribute when defining a default in a partial', function () {
        $output = renderLatte([
            'figure' => '<figcaption n:ifcontent n:slot="caption">Default caption</figcaption>',
            'main' => "{embed file 'figure'}{/embed}",
        ]);

        expect(trim($output))->toBe('<figcaption>Default caption</figcaption>');
    });

    test('a filled n:slot overrides the default element content', function () {
        $output = renderLatte([
            'figure' => '<figure><figcaption n:slot="caption">Default</figcaption></figure>',
            'main' => "{embed file 'figure'}<figcaption n:slot=\"caption\">Filled</figcaption>{/embed}",
        ]);

        expect(trim($output))->toBe('<figure><figcaption>Filled</figcaption></figure>');
    });

    test('supports the n:inner-slot attribute when defining a default in a partial', function () {
        $output = renderLatte([
            'figure' => '<figure><figcaption n:inner-slot="caption">Default</figcaption></figure>',
            'main' => "{embed file 'figure'}{/embed}",
        ]);

        expect(trim($output))->toBe('<figure><figcaption>Default</figcaption></figure>');
    });

    test('an n:inner-slot default is overridden when the slot is filled', function () {
        $output = renderLatte([
            'figure' => '<figure><figcaption n:inner-slot="caption">Default</figcaption></figure>',
            'main' => "{embed file 'figure'}{slot caption}Filled{/slot}{/embed}",
        ]);

        expect(trim($output))->toBe('<figure><figcaption>Filled</figcaption></figure>');
    });

    test('an n:inner-slot default is overridden by an n:slot fill', function () {
        $output = renderLatte([
            'figure' => '<figure><figcaption n:inner-slot="caption">Default</figcaption></figure>',
            'main' => "{embed file 'figure'}<span n:slot=\"caption\">Filled</span>{/embed}",
        ]);

        expect(trim($output))->toBe('<figure><figcaption><span>Filled</span></figcaption></figure>');
    });

    test('n:inner-slot behaves identically to n:inner-block', function () {
        $withBlock = renderLatte([
            'figure' => '<figure><figcaption n:inner-block="caption">Default</figcaption></figure>',
            'main' => "{embed file 'figure'}{/embed}",
        ]);
        $withSlot = renderLatte([
            'figure' => '<figure><figcaption n:inner-slot="caption">Default</figcaption></figure>',
            'main' => "{embed file 'figure'}{/embed}",
        ]);

        expect($withSlot)->toBe($withBlock);
    });

    test('n:slot behaves identically to n:block', function () {
        $withBlock = renderLatte([
            'figure' => '<figcaption n:block="caption">Default</figcaption>',
            'main' => "{embed file 'figure'}<figcaption n:block=\"caption\">Filled</figcaption>{/embed}",
        ]);
        $withSlot = renderLatte([
            'figure' => '<figcaption n:slot="caption">Default</figcaption>',
            'main' => "{embed file 'figure'}<figcaption n:slot=\"caption\">Filled</figcaption>{/embed}",
        ]);

        expect($withSlot)->toBe($withBlock);
    });
});
