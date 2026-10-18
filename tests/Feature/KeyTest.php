<?php

use Daun\StatamicLatte\Latte\Extensions\KeyExtension;
use Daun\StatamicLatte\Latte\Extensions\Nodes\KeyNode;
use Latte\CompileException;
use Latte\Engine;
use Latte\Loaders\StringLoader;

describe('n:key', function () {
    test('adds a key and an id derived from the rendered outer HTML', function () {
        $key = KeyNode::hash('<div class="card"><strong>Hello Philipp</strong></div>');

        $this->latte('<div class="card" n:key><strong>Hello {$name}</strong></div>', ['name' => 'Philipp'])
            ->assertSee(
                "<div class=\"card\" id=\"key-{$key}\" key=\"{$key}\"><strong>Hello Philipp</strong></div>",
                false,
            );
    });

    test('keeps an existing id', function () {
        $key = KeyNode::hash('<article id="intro">Hello</article>');

        $this->latte('<article id="intro" n:key>Hello</article>')
            ->assertSee("<article id=\"intro\" key=\"{$key}\">Hello</article>", false)
            ->assertDontSee('id="key-', false);
    });

    test('uses rendered rather than template content', function () {
        $template = '<div n:key>{$value}</div>';
        $first = (string) $this->latte($template, ['value' => 'First']);
        $second = (string) $this->latte($template, ['value' => 'Second']);

        expect($first)->not->toBe($second)
            ->and($first)->toContain(KeyNode::hash('<div>First</div>'))
            ->and($second)->toContain(KeyNode::hash('<div>Second</div>'));
    });

    test('creates a separate key for every n:foreach element', function () {
        $first = KeyNode::hash('<li>First</li>');
        $second = KeyNode::hash('<li>Second</li>');

        $this->latte(
            '<li n:foreach="$items as $item" n:key>{$item}</li>',
            ['items' => ['First', 'Second']],
        )
            ->assertSee("key=\"{$first}\"", false)
            ->assertSee("key=\"{$second}\"", false);
    });

    test('includes attributes when hashing void elements', function () {
        $first = KeyNode::hash('<img src="first.jpg">');
        $second = KeyNode::hash('<img src="second.jpg">');

        $this->latte('<img n:foreach="$sources as $source" src="{$source}" n:key>', [
            'sources' => ['first.jpg', 'second.jpg'],
        ])
            ->assertSee("key=\"{$first}\"", false)
            ->assertSee("key=\"{$second}\"", false);
    });
});

describe('n:key condition', function () {
    test('adds a key when the condition is truthy', function () {
        $key = KeyNode::hash('<div class="card">Hello Philipp</div>');

        $this->latte('<div class="card" n:key="$live">Hello {$name}</div>', ['live' => true, 'name' => 'Philipp'])
            ->assertSee("<div class=\"card\" id=\"key-{$key}\" key=\"{$key}\">Hello Philipp</div>", false);
    });

    test('renders the element untouched when the condition is falsy', function () {
        $this->latte('<div class="card" n:key="$live">Hello {$name}</div>', ['live' => false, 'name' => 'Philipp'])
            ->assertSee('<div class="card">Hello Philipp</div>', false)
            ->assertDontSee('key', false);
    });

    test('accepts function call conditions', function () {
        $this->latte('<div n:key="in_array(1, [1, 2])">A</div><p n:key="in_array(3, [1, 2])">B</p>')
            ->assertSee('key="'.KeyNode::hash('<div>A</div>').'"', false)
            ->assertSee('<p>B</p>', false);
    });

    test('renders void elements untouched when the condition is falsy', function () {
        $this->latte('<img src="{$src}" n:key="false">', ['src' => 'a.jpg'])
            ->assertSee('<img src="a.jpg">', false)
            ->assertDontSee('key', false);
    });

    test('evaluates the condition for every n:foreach element', function () {
        $this->latte(
            '<li n:foreach="$items as $i => $item" n:key="$i === 0">{$item}</li>',
            ['items' => ['First', 'Second']],
        )
            ->assertSee('key="'.KeyNode::hash('<li>First</li>').'">First</li>', false)
            ->assertSee('<li>Second</li>', false);
    });

    test('evaluates the condition only once', function () {
        $calls = 0;
        $check = function () use (&$calls) {
            $calls++;

            return true;
        };

        (string) $this->latte('<div n:key="$check()">Hello</div>', ['check' => $check]);

        expect($calls)->toBe(1);
    });

    test('renders nested conditional keys', function () {
        $inner = KeyNode::hash('<span>Hi</span>');

        $this->latte('<section n:key="false"><div n:key="true"><span n:key="true">{$text}</span></div></section>', ['text' => 'Hi'])
            ->assertSee('<section><div id="key-', false)
            ->assertSee("<span id=\"key-{$inner}\" key=\"{$inner}\">Hi</span>", false);
    });

    test('compiles nested element content only once', function () {
        $engine = new Engine;
        $engine->setLoader(new StringLoader);
        $engine->addExtension(new KeyExtension);

        $code = $engine->compile('<section n:key="$a"><div n:key="$b"><span n:key="$c">{$text}</span></div></section>');

        expect(substr_count($code, 'escapeText($text)'))->toBe(1);
    });
});

describe('key hash', function () {
    test('is 8 hex characters', function () {
        expect(KeyNode::hash('<div>Hello</div>'))->toMatch('/^[0-9a-f]{8}$/');
    });
});

describe('key guards', function () {
    test('rejects an existing key on n:key elements', function () {
        expect(fn () => $this->latte('<div key="mine" n:key>Hello</div>')->__toString())
            ->toThrow(CompileException::class, 'It is not possible to combine key with n:key');
    });

    test('rejects extra arguments after the condition', function () {
        expect(fn () => $this->latte('<div n:key="$a, $b">Hello</div>')->__toString())
            ->toThrow(CompileException::class, 'Unexpected arguments');
    });

    test('does not expose paired tag syntax', function () {
        expect(fn () => $this->latte('{key}<div>Hello</div>{/key}')->__toString())
            ->toThrow(CompileException::class, 'Unexpected tag {key}');
    });
});
