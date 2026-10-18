<?php

use Latte\CompileException;
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

    test('matches an inline block repeated with loop and caller-local data', function () {
        $withBlock = renderLatte([
            'table' => '<table>{foreach $rows as $value}<tr>{block row}<td>{$value}</td>{/block}</tr>{/foreach}</table>',
            'main' => "{var \$class = 'custom'}{embed file 'table', rows: ['Alpha', 'Beta']}{block row}<td class={\$class}>{\$value}</td>{/block}{/embed}",
        ]);
        $withSlot = renderLatte([
            'table' => '<table>{foreach $rows as $value}<tr>{slot row}<td>{$value}</td>{/slot}</tr>{/foreach}</table>',
            'main' => "{var \$class = 'custom'}{embed file 'table', rows: ['Alpha', 'Beta']}{slot row}<td class={\$class}>{\$value}</td>{/slot}{/embed}",
        ]);

        expect($withSlot)->toBe($withBlock)
            ->and(trim($withSlot))->toBe('<table><tr><td class="custom">Alpha</td></tr><tr><td class="custom">Beta</td></tr></table>');
    });

    test('matches an included block repeated with explicit and caller-local data', function () {
        $table = '{define row, $cell}<td>{$cell}</td>{/define}<table>{foreach $rows as $value}<tr>{include #row, cell: $value}</tr>{/foreach}</table>';
        $withBlock = renderLatte([
            'table' => $table,
            'main' => "{var \$class = 'custom'}{embed file 'table', rows: ['Alpha', 'Beta']}{block row}<td class={\$class}>{\$cell}</td>{/block}{/embed}",
        ]);
        $withSlot = renderLatte([
            'table' => $table,
            'main' => "{var \$class = 'custom'}{embed file 'table', rows: ['Alpha', 'Beta']}{slot row}<td class={\$class}>{\$cell}</td>{/slot}{/embed}",
        ]);

        expect($withSlot)->toBe($withBlock)
            ->and(trim($withSlot))->toBe('<table><tr><td class="custom">Alpha</td></tr><tr><td class="custom">Beta</td></tr></table>');
    });

    test('repeats the inline default for every iteration when not filled', function () {
        $withBlock = renderLatte([
            'table' => '<table>{foreach $rows as $value}<tr>{block row}<td>{$value}</td>{/block}</tr>{/foreach}</table>',
            'main' => "{embed file 'table', rows: ['Alpha', 'Beta']}{/embed}",
        ]);
        $withSlot = renderLatte([
            'table' => '<table>{foreach $rows as $value}<tr>{slot row}<td>{$value}</td>{/slot}</tr>{/foreach}</table>',
            'main' => "{embed file 'table', rows: ['Alpha', 'Beta']}{/embed}",
        ]);

        expect($withSlot)->toBe($withBlock)
            ->and(trim($withSlot))->toBe('<table><tr><td>Alpha</td></tr><tr><td>Beta</td></tr></table>');
    });

    test('lets partial loop data shadow a same-named caller variable', function () {
        $withBlock = renderLatte([
            'table' => '{foreach $rows as $value}{block row}{/block}{/foreach}',
            'main' => "{var \$value = 'caller'}{embed file 'table', rows: ['Alpha', 'Beta']}{block row}[{\$value}]{/block}{/embed}",
        ]);
        $withSlot = renderLatte([
            'table' => '{foreach $rows as $value}{slot row}{/slot}{/foreach}',
            'main' => "{var \$value = 'caller'}{embed file 'table', rows: ['Alpha', 'Beta']}{slot row}[{\$value}]{/slot}{/embed}",
        ]);

        expect($withSlot)->toBe($withBlock)
            ->and(trim($withSlot))->toBe('[Alpha][Beta]');
    });

    test('lets explicit include arguments shadow a same-named caller variable', function () {
        $table = '{define row, $cell}{/define}{foreach $rows as $value}{include #row, cell: $value}{/foreach}';
        $withBlock = renderLatte([
            'table' => $table,
            'main' => "{var \$cell = 'caller'}{embed file 'table', rows: ['Alpha', 'Beta']}{block row}[{\$cell}]{/block}{/embed}",
        ]);
        $withSlot = renderLatte([
            'table' => $table,
            'main' => "{var \$cell = 'caller'}{embed file 'table', rows: ['Alpha', 'Beta']}{slot row}[{\$cell}]{/slot}{/embed}",
        ]);

        expect($withSlot)->toBe($withBlock)
            ->and(trim($withSlot))->toBe('[Alpha][Beta]');
    });

    test('reads caller variables in an n:slot fill', function () {
        $withBlock = renderLatte([
            'figure' => '<figure><figcaption n:block="caption">Default</figcaption></figure>',
            'main' => "{var \$label = 'Filled'}{embed file 'figure'}<figcaption n:block=\"caption\">{\$label}</figcaption>{/embed}",
        ]);
        $withSlot = renderLatte([
            'figure' => '<figure><figcaption n:slot="caption">Default</figcaption></figure>',
            'main' => "{var \$label = 'Filled'}{embed file 'figure'}<figcaption n:slot=\"caption\">{\$label}</figcaption>{/embed}",
        ]);

        expect($withSlot)->toBe($withBlock)
            ->and(trim($withSlot))->toBe('<figure><figcaption>Filled</figcaption></figure>');
    });

    test('reads caller variables in an n:inner-slot fill', function () {
        $withBlock = renderLatte([
            'figure' => '<figure><figcaption n:inner-block="caption">Default</figcaption></figure>',
            'main' => "{var \$label = 'Filled'}{embed file 'figure'}<b n:inner-block=\"caption\">{\$label}</b>{/embed}",
        ]);
        $withSlot = renderLatte([
            'figure' => '<figure><figcaption n:inner-slot="caption">Default</figcaption></figure>',
            'main' => "{var \$label = 'Filled'}{embed file 'figure'}<b n:inner-slot=\"caption\">{\$label}</b>{/embed}",
        ]);

        expect($withSlot)->toBe($withBlock)
            ->and(trim($withSlot))->toBe('<figure><figcaption>Filled</figcaption></figure>');
    });

    test('includes the partial default via {include parent}', function () {
        $withBlock = renderLatte([
            'figure' => '<figcaption>{block caption}Default{/block}</figcaption>',
            'main' => "{var \$prefix = 'Caller'}{embed file 'figure'}{block caption}{\$prefix}: {include parent}{/block}{/embed}",
        ]);
        $withSlot = renderLatte([
            'figure' => '<figcaption>{slot caption}Default{/slot}</figcaption>',
            'main' => "{var \$prefix = 'Caller'}{embed file 'figure'}{slot caption}{\$prefix}: {include parent}{/slot}{/embed}",
        ]);

        expect($withSlot)->toBe($withBlock)
            ->and(trim($withSlot))->toBe('<figcaption>Caller: Default</figcaption>');
    });

    test('keeps native dynamic block behavior for dynamic slot names', function () {
        $withBlock = renderLatte([
            'figure' => '<figcaption>{block caption}Default{/block}</figcaption>',
            'main' => "{var \$name = 'caption'}{embed file 'figure'}{block \$name}Filled{/block}{/embed}",
        ]);
        $withSlot = renderLatte([
            'figure' => '<figcaption>{slot caption}Default{/slot}</figcaption>',
            'main' => "{var \$name = 'caption'}{embed file 'figure'}{slot \$name}Filled{/slot}{/embed}",
        ]);

        expect($withSlot)->toBe($withBlock);
    });

    test('keeps native local block behavior for local slots', function () {
        $withBlock = renderLatte([
            'main' => "{var \$label = 'Hi'}{block local greeting}{\$label}{/block}",
        ]);
        $withSlot = renderLatte([
            'main' => "{var \$label = 'Hi'}{slot local greeting}{\$label}{/slot}",
        ]);

        expect($withSlot)->toBe($withBlock)
            ->and(trim($withSlot))->toBe('Hi');
    });

    test('applies filters to an anonymous slot like an anonymous block', function () {
        $withBlock = renderLatte([
            'main' => "{var \$label = 'hi'}{block|stripHtml|upper}<b>{\$label}</b> there{/block}",
        ]);
        $withSlot = renderLatte([
            'main' => "{var \$label = 'hi'}{slot|stripHtml|upper}<b>{\$label}</b> there{/slot}",
        ]);

        expect($withSlot)->toBe($withBlock)
            ->and(trim($withSlot))->toBe('HI THERE');
    });

    test('reports duplicate slots with slot wording', function () {
        expect(fn () => renderLatte([
            'figure' => '{slot caption}{/slot}',
            'main' => "{embed file 'figure'}{slot caption}A{/slot}{slot caption}B{/slot}{/embed}",
        ]))->toThrow(CompileException::class, "Cannot redeclare slot 'caption'");
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

    test('reads local variables outside an embed like a plain block', function () {
        $withBlock = renderLatte([
            'main' => "{var \$label = 'Hi'}{block greeting}{\$label}{/block}",
        ]);
        $withSlot = renderLatte([
            'main' => "{var \$label = 'Hi'}{slot greeting}{\$label}{/slot}",
        ]);

        expect($withSlot)->toBe($withBlock)
            ->and(trim($withSlot))->toBe('Hi');
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
