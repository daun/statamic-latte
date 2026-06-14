<?php

use Latte\Engine;

/**
 * Latte 3.1 introduced "Smart HTML Attributes" — type-aware attribute
 * rendering (null removal, boolean attributes, array class/style, data-* JSON,
 * ARIA stringification, runtime type checks) plus the nullsafe filter operator
 * `?|`, `n:elseif`, and the brace `n:attribute={...}` form.
 *
 * Because the inline `(s:...)` sub-expression is rewritten in the source loader
 * before Latte compiles, it slots into all of these new constructs as an
 * ordinary expression. These tests pin that down.
 *
 * @see https://blog.nette.org/en/latte-3-1-when-a-templating-system-truly-understands-html
 */
describe('version', function () {
    test('requires latte 3.1+', function () {
        expect(version_compare(Engine::VERSION, '3.1', '>='))->toBeTrue();
    });
});

describe('booleans', function () {
    test('renders the bare boolean attribute on a truthy result', function () {
        $this->latte(<<<'LATTE'
            <input type="checkbox" disabled={(s:collection:count in: pages)}>
        LATTE)
            ->assertSee('<input type="checkbox" disabled>', false);
    });

    test('removes the boolean attribute on a zero result', function () {
        $this->latte(<<<'LATTE'
            <input type="checkbox" disabled={(s:collection:count in: pages, title:contains: zzzzz)}>
        LATTE)
            ->assertSee('<input type="checkbox">', false)
            ->assertDontSee('disabled', false);
    });
});

describe('nulls', function () {
    test('removes the attribute on a null expression', function () {
        $this->latte(<<<'LATTE'
            <span title={(s:collection:count in: pages) > 99 ? 'big' : null}>x</span>
        LATTE)
            ->assertSee('<span>x</span>', false)
            ->assertDontSee('title', false);
    });

    test('keeps the attribute on a non-null expression', function () {
        $this->latte(<<<'LATTE'
            <span title={(s:collection:count in: pages) > 1 ? 'big' : null}>x</span>
        LATTE)
            ->assertSee('<span title="big">x</span>', false);
    });
});

describe('arrays', function () {
    test('toggles class entries from tag-derived conditions', function () {
        $this->latte(<<<'LATTE'
            <div class={[btn, active => (s:collection:count in: pages) > 1, off => (s:collection:count in: pages) > 99]}>x</div>
        LATTE)
            ->assertSee('class="btn active"', false);
    });

    test('toggles style entries from tag-derived conditions', function () {
        $this->latte(<<<'LATTE'
            <div style={[display => (s:collection:count in: pages) > 1 ? block : null]}>x</div>
        LATTE)
            ->assertSee('style="display: block"', false);
    });
});

describe('data attributes', function () {
    test('json-encodes an array containing a tag result', function () {
        $this->latte(<<<'LATTE'
            <div data-info={[count => (s:collection:count in: pages)]}>x</div>
        LATTE)
            ->assertSee('data-info=\'{"count":2}\'', false);
    });
});

describe('aria', function () {
    test('stringifies a truthy comparison to "true"', function () {
        $this->latte(<<<'LATTE'
            <button aria-expanded={(s:collection:count in: pages) > 1}>x</button>
        LATTE)
            ->assertSee('aria-expanded="true"', false);
    });

    test('stringifies a falsey comparison to "false"', function () {
        $this->latte(<<<'LATTE'
            <button aria-expanded={(s:collection:count in: pages) > 99}>x</button>
        LATTE)
            ->assertSee('aria-expanded="false"', false);
    });
});

describe('nullsafe filter', function () {
    test('applies a filter to a tag result via ?|', function () {
        $this->latte(<<<'LATTE'
            <a title={(s:link to: 'snacks')?|upper}>x</a>
        LATTE)
            ->assertSee('title="/SNACKS"', false);
    });
});

describe('type checking', function () {
    test('rejects an iterable tag result in a scalar attribute', function () {
        // `nav:breadcrumbs` returns an array; Latte 3.1 refuses to print it
        // into a string attribute rather than emitting `title="Array"`.
        $this->latte(<<<'LATTE'
            <span title={(s:nav:breadcrumbs)}>x</span>
        LATTE);
    })->throws(ErrorException::class, 'array is not allowed');
});

describe('n:elseif', function () {
    test('chains tag-derived conditions across n:if / n:elseif / n:else', function () {
        $this->latte(<<<'LATTE'
            <p n:if="(s:collection:count in: pages) > 99">a</p>
            <p n:elseif="(s:collection:count in: pages) > 1">b</p>
            <p n:else>c</p>
        LATTE)
            ->assertSee('<p>b</p>', false)
            ->assertDontSee('<p>a</p>', false)
            ->assertDontSee('<p>c</p>', false);
    });
});

describe('brace syntax', function () {
    test('accepts a tag sub-expression in the {...} form of n:if', function () {
        $this->latte(<<<'LATTE'
            <p n:if={(s:collection:count in: pages) > 1}>yes</p>
        LATTE)
            ->assertSee('<p>yes</p>', false);
    });
});
