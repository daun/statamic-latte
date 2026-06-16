<?php

/**
 * The inline `(s:...)` sub-expression is rewritten in the source loader, before
 * Latte sees the template. These tests pin down that it therefore also works
 * inside Latte's `n:` attributes (which Latte expands into ordinary expressions
 * at compile time) — `n:if`, `n:foreach`, `n:attr`, `n:href`, interpolated
 * attribute strings — and that it keeps working when wrapped in the block-style
 * `{s:tagName}` tag.
 *
 * Note: Latte's `n:href` is intentionally not covered — it is a Nette
 * application-router feature that resolves presenter/route actions, unrelated
 * to building plain href strings, and throws without a Nette router.
 */
describe('n:foreach', function () {
    test('iterates a tag sub-expression directly', function () {
        $this->latte(<<<'LATTE'
            <ul><li n:foreach="(s:collection from: pages, sort: title) as $entry">{$entry->title}</li></ul>
        LATTE)
            ->assertSeeInOrder(['<li>Testable</li>', '<li>Testable With Layout</li>'], false);
    });

    test('iterates a tag sub-expression captured into a variable', function () {
        $this->latte(<<<'LATTE'
            {var $entries = (s:collection from: pages, sort: title)}
            <ul><li n:foreach="$entries as $entry">{$entry->title}</li></ul>
        LATTE)
            ->assertSeeInOrder(['<li>Testable</li>', '<li>Testable With Layout</li>'], false);
    });
});

describe('n:if', function () {
    test('uses a scalar tag result as a boolean condition', function () {
        $this->latte(<<<'LATTE'
            <p n:if="(s:collection:count in: pages) > 1">many</p>
        LATTE)
            ->assertSee('<p>many</p>', false);
    });

    test('removes the element on a falsey comparison', function () {
        $this->latte(<<<'LATTE'
            <p n:if="(s:collection:count in: pages) > 99">many</p>
        LATTE)
            ->assertDontSee('many');
    });

    test('uses a tag result directly as a truthy condition', function () {
        // A non-zero count and a non-empty string are both truthy on their own.
        $this->latte(<<<'LATTE'
            <p n:if="(s:collection:count in: pages)">counted</p>
        LATTE)
            ->assertSee('<p>counted</p>', false);

        $this->latte(<<<'LATTE'
            <p n:if="(s:link to: 'snacks')">linked</p>
        LATTE)
            ->assertSee('<p>linked</p>', false);
    });

    test('treats a zero or empty tag result as falsey', function () {
        // count of a no-match filter is 0 -> falsey.
        $this->latte(<<<'LATTE'
            <p n:if="(s:collection:count in: pages, title:contains: zzzzz)">yes</p>
            <p n:else>no</p>
        LATTE)
            ->assertSee('<p>no</p>', false)
            ->assertDontSee('<p>yes</p>', false);

        // an empty collection result is an empty array -> falsey.
        $this->latte(<<<'LATTE'
            <p n:if="(s:collection from: pages, title:contains: zzzzz)">yes</p>
            <p n:else>no</p>
        LATTE)
            ->assertSee('<p>no</p>', false)
            ->assertDontSee('<p>yes</p>', false);
    });

    test('works with n:else chains', function () {
        $this->latte(<<<'LATTE'
            <p n:if="(s:collection:count in: pages) > 99">lots</p>
            <p n:else>not lots</p>
        LATTE)
            ->assertSee('<p>not lots</p>', false)
            ->assertDontSee('<p>lots</p>', false);
    });
});

describe('n:attr', function () {
    test('builds an attribute value from a tag sub-expression', function () {
        $this->latte(<<<'LATTE'
            <a n:attr="href: (s:link to: 'snacks')">Snacks</a>
        LATTE)
            ->assertSee('<a href="/snacks">Snacks</a>', false);
    });

    test('combines a tag sub-expression with other attributes', function () {
        $this->latte(<<<'LATTE'
            <a n:attr="href: (s:link to: 'snacks'), class: 'btn'">Snacks</a>
        LATTE)
            ->assertSee('href="/snacks"', false)
            ->assertSee('class="btn"', false);
    });
    test('spreads an associative array passed as a Content object', function () {
        $this->latte('<div n:attr="$attrs">x</div>', [
            'attrs' => ['class' => 'big', 'data-id' => 7, 'hidden' => true, 'title' => null],
        ])
            ->assertSee('<div class="big" data-id="7" hidden>x</div>', false);
    });

    test('still renders a keyed n:attr after the unwrap pass', function () {
        $this->latte('<a n:attr="href: $url, class: \'btn\'">x</a>', ['url' => '/go'])
            ->assertSee('<a href="/go" class="btn">x</a>', false);
    });
});

describe('interpolation', function () {
    test('builds an href string by interpolating the tag result', function () {
        $this->latte(<<<'LATTE'
            <a href="{(s:link to: 'snacks')}">Snacks</a>
        LATTE)
            ->assertSee('<a href="/snacks">Snacks</a>', false);
    });

    test('concatenates the tag result into a larger href', function () {
        $this->latte(<<<'LATTE'
            <a href="https://example.com{(s:link to: 'snacks')}">Snacks</a>
        LATTE)
            ->assertSee('<a href="https://example.com/snacks">Snacks</a>', false);
    });
});

describe('block tag', function () {
    test('survives n:if inside a scalar {s:link} block', function () {
        $this->latte(<<<'LATTE'
            {s:link to: "snacks"}
                <span n:if="(s:collection:count in: pages) > 1">{$value}</span>
            {/s:link}
        LATTE)
            ->assertSee('<span>/snacks</span>', false);
    });

    test('survives n:foreach inside an iterable {s:collection} block', function () {
        $this->latte(<<<'LATTE'
            {s:collection from: pages, sort: title}
                <a n:attr="href: (s:link to: 'snacks')">{$value->title}</a>
            {/s:collection}
        LATTE)
            ->assertSee('href="/snacks"', false)
            ->assertSee('Testable');
    });
});
