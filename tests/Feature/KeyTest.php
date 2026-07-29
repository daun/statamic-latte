<?php

use Latte\CompileException;

describe('n:key', function () {
    test('adds a key and an id derived from the rendered outer HTML', function () {
        $key = hash('xxh128', '<div class="card"><strong>Hello Philipp</strong></div>');

        $this->latte('<div class="card" n:key><strong>Hello {$name}</strong></div>', ['name' => 'Philipp'])
            ->assertSee(
                "<div class=\"card\" id=\"key-{$key}\" key=\"{$key}\"><strong>Hello Philipp</strong></div>",
                false,
            );
    });

    test('keeps an existing id', function () {
        $key = hash('xxh128', '<article id="intro">Hello</article>');

        $this->latte('<article id="intro" n:key>Hello</article>')
            ->assertSee("<article id=\"intro\" key=\"{$key}\">Hello</article>", false)
            ->assertDontSee('id="key-', false);
    });

    test('uses rendered rather than template content', function () {
        $template = '<div n:key>{$value}</div>';
        $first = (string) $this->latte($template, ['value' => 'First']);
        $second = (string) $this->latte($template, ['value' => 'Second']);

        expect($first)->not->toBe($second)
            ->and($first)->toContain(hash('xxh128', '<div>First</div>'))
            ->and($second)->toContain(hash('xxh128', '<div>Second</div>'));
    });

    test('creates a separate key for every n:foreach element', function () {
        $first = hash('xxh128', '<li>First</li>');
        $second = hash('xxh128', '<li>Second</li>');

        $this->latte(
            '<li n:foreach="$items as $item" n:key>{$item}</li>',
            ['items' => ['First', 'Second']],
        )
            ->assertSee("key=\"{$first}\"", false)
            ->assertSee("key=\"{$second}\"", false);
    });

    test('includes attributes when hashing void elements', function () {
        $first = hash('xxh128', '<img src="first.jpg">');
        $second = hash('xxh128', '<img src="second.jpg">');

        $this->latte('<img n:foreach="$sources as $source" src="{$source}" n:key>', [
            'sources' => ['first.jpg', 'second.jpg'],
        ])
            ->assertSee("key=\"{$first}\"", false)
            ->assertSee("key=\"{$second}\"", false);
    });
});

describe('key guards', function () {
    test('rejects an existing key on n:key elements', function () {
        expect(fn () => $this->latte('<div key="mine" n:key>Hello</div>')->__toString())
            ->toThrow(CompileException::class, 'It is not possible to combine key with n:key');
    });

    test('does not expose paired tag syntax', function () {
        expect(fn () => $this->latte('{key}<div>Hello</div>{/key}')->__toString())
            ->toThrow(CompileException::class, 'Unexpected tag {key}');
    });
});
