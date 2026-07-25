<?php

use Daun\StatamicLatte\Latte\Support\Html;
use Latte\CompileException;

/**
 * {iftext} and n:iftext keep their content only when it renders something
 * visible: text left over after stripping tags, or an element that renders on
 * its own (image, form control, embed).
 */
describe('n:iftext', function () {
    test('keeps an element wrapping text', function () {
        $this->latte(<<<'LATTE'
            <div n:iftext><h2>Hello</h2></div>
        LATTE)
            ->assertSee('<div><h2>Hello</h2></div>', false);
    });

    test('removes an element wrapping empty markup', function () {
        $this->latte(<<<'LATTE'
            <div n:iftext><h2></h2><p>  </p></div>
        LATTE)
            ->assertDontSee('<div', false);
    });

    test('removes an element wrapping only whitespace entities', function () {
        $this->latte(<<<'LATTE'
            <div n:iftext><p>&nbsp;</p><br></div>
        LATTE)
            ->assertDontSee('<div', false);
    });

    test('removes an element wrapping only comments', function () {
        $this->latte(<<<'LATTE'
            <div n:iftext><!-- nothing to see --></div>
        LATTE)
            ->assertDontSee('<div', false);
    });

    test('keeps an element wrapping a self-rendering element', function () {
        $this->latte(<<<'LATTE'
            <div n:iftext><img src="cat.jpg" alt=""></div>
        LATTE)
            ->assertSee('<img src="cat.jpg" alt="">', false);
    });

    test('keeps an element wrapping a form', function () {
        $this->latte(<<<'LATTE'
            <div n:iftext><form><input type="text"></form></div>
        LATTE)
            ->assertSee('<div><form>', false);
    });

    test('keeps an element wrapping an inline svg', function () {
        $this->latte(<<<'LATTE'
            <div n:iftext><svg viewBox="0 0 1 1"><path d="M0 0"/></svg></div>
        LATTE)
            ->assertSee('<div><svg', false);
    });

    test('tests the rendered output, not the source markup', function () {
        $this->latte(<<<'LATTE'
            <div n:iftext><p>{$empty}</p></div>
        LATTE, ['empty' => ''])
            ->assertDontSee('<div', false);

        $this->latte(<<<'LATTE'
            <div n:iftext><p>{$filled}</p></div>
        LATTE, ['filled' => 'Hi'])
            ->assertSee('<div><p>Hi</p></div>', false);
    });

    test('keeps the element attributes when kept', function () {
        $this->latte(<<<'LATTE'
            <div class="card" n:iftext>Hello</div>
        LATTE)
            ->assertSee('<div class="card">Hello</div>', false);
    });

    test('nests without leaking buffers', function () {
        $this->latte(<<<'LATTE'
            <section n:iftext><div n:iftext><p></p></div><span>text</span></section>
        LATTE)
            ->assertSee('<section><span>text</span></section>', false);

        $this->latte(<<<'LATTE'
            <section n:iftext><div n:iftext><p></p></div></section>
        LATTE)
            ->assertDontSee('<section', false);
    });

    test('renders the else branch when blank', function () {
        $this->latte(<<<'LATTE'
            <div n:iftext><p></p></div>
        LATTE)
            ->assertDontSee('<div', false);
    });

    test('fails to compile on a void element, which can never hold text', function () {
        expect(fn () => $this->latte('<img src="cat.jpg" n:iftext>')->assertSee(''))
            ->toThrow(CompileException::class, 'Unnecessary n:iftext on empty element <img>');
    });

    test('removes an element with no content at all', function () {
        $this->latte(<<<'LATTE'
            <div n:iftext></div>
        LATTE)
            ->assertDontSee('<div', false);
    });
});

describe('n:inner-iftext', function () {
    test('keeps the element but drops blank content', function () {
        $this->latte(<<<'LATTE'
            <div n:inner-iftext><p></p></div>
        LATTE)
            ->assertSee('<div></div>', false)
            ->assertDontSee('<p>', false);
    });

    test('keeps content holding text', function () {
        $this->latte(<<<'LATTE'
            <div n:inner-iftext><p>Hello</p></div>
        LATTE)
            ->assertSee('<div><p>Hello</p></div>', false);
    });
});

describe('{iftext}', function () {
    test('renders content holding text', function () {
        $this->latte(<<<'LATTE'
            {iftext}<h2>Hello</h2>{/iftext}
        LATTE)
            ->assertSee('<h2>Hello</h2>', false);
    });

    test('skips blank content', function () {
        $this->latte(<<<'LATTE'
            before{iftext}<h2></h2>{/iftext}after
        LATTE)
            ->assertSee('beforeafter')
            ->assertDontSee('<h2>', false);
    });

    test('renders content holding a self-rendering element', function () {
        $this->latte(<<<'LATTE'
            {iftext}<img src="cat.jpg">{/iftext}
        LATTE)
            ->assertSee('<img src="cat.jpg">', false);
    });

    test('renders the else branch when blank', function () {
        $this->latte(<<<'LATTE'
            {iftext}<p>  </p>{else}<p>Nothing here</p>{/iftext}
        LATTE)
            ->assertSee('<p>Nothing here</p>', false);
    });

    test('skips the else branch when text is present', function () {
        $this->latte(<<<'LATTE'
            {iftext}<p>Hello</p>{else}<p>Nothing here</p>{/iftext}
        LATTE)
            ->assertSee('<p>Hello</p>', false)
            ->assertDontSee('Nothing here');
    });

    test('nests', function () {
        $this->latte(<<<'LATTE'
            {iftext}{iftext}<p></p>{/iftext}<span>text</span>{/iftext}
        LATTE)
            ->assertSee('<span>text</span>', false)
            ->assertDontSee('<p>', false);
    });
});

describe('Html::hasText()', function () {
    test('detects visible text', function () {
        expect(Html::hasText('<h2>Hello</h2>'))->toBeTrue()
            ->and(Html::hasText('bare text'))->toBeTrue()
            ->and(Html::hasText('<p><em>nested</em></p>'))->toBeTrue();
    });

    test('ignores markup that renders nothing', function () {
        expect(Html::hasText(null))->toBeFalse()
            ->and(Html::hasText(''))->toBeFalse()
            ->and(Html::hasText('   '))->toBeFalse()
            ->and(Html::hasText('<p></p>'))->toBeFalse()
            ->and(Html::hasText('<p>&nbsp;</p>'))->toBeFalse()
            ->and(Html::hasText("<p>\u{200B}</p>"))->toBeFalse()
            ->and(Html::hasText('<div><span> </span></div>'))->toBeFalse()
            ->and(Html::hasText('<!-- comment -->'))->toBeFalse()
            ->and(Html::hasText('<p><br></p>'))->toBeFalse();
    });

    test('ignores style and template contents', function () {
        expect(Html::hasText('<style>.a { color: red }</style>'))->toBeFalse()
            ->and(Html::hasText('<template><p>Hi</p></template>'))->toBeFalse();
    });

    test('detects elements that render on their own', function () {
        expect(Html::hasText('<img src="cat.jpg">'))->toBeTrue()
            ->and(Html::hasText('<svg><path d="M0 0"/></svg>'))->toBeTrue()
            ->and(Html::hasText('<video src="a.mp4"></video>'))->toBeTrue()
            ->and(Html::hasText('<iframe src="a.html"></iframe>'))->toBeTrue()
            ->and(Html::hasText('<form><input></form>'))->toBeTrue()
            ->and(Html::hasText('<select><option></option></select>'))->toBeTrue()
            ->and(Html::hasText('<hr>'))->toBeTrue();
    });

    test('honours a customised element list', function () {
        $original = Html::$renderingElements;
        Html::$renderingElements = ['img'];

        expect(Html::hasText('<img src="cat.jpg">'))->toBeTrue()
            ->and(Html::hasText('<hr>'))->toBeFalse();

        Html::$renderingElements = $original;
    });
});
