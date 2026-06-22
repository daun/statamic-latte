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
});
