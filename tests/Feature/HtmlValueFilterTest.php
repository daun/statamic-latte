<?php

use Statamic\Facades\Entry;

/**
 * How an HtmlValue survives Latte's built-in filters.
 *
 * Latte's own filters branch on HtmlStringable, so marking a field as HTML
 * changes what they do with it. Three groups, each pinned below:
 *
 *   1. Content-aware filters (FilterInfo in the signature: stripTags, trim,
 *      spaceless, indent, replace, repeat) see contentType=Html and re-wrap
 *      their result in Latte\Runtime\Html — the markup survives the filter.
 *   2. stripHtml is content-aware too but flips contentType to Text: it
 *      decodes entities, so its result must be (and is) escaped on print.
 *   3. Classic filters (upper, truncate, ...) take the value as a plain string
 *      and return a plain string — output is escaped again, as before.
 *
 * `|noescape` keeps working in every one of those cases, since it is a
 * compile-time flag on the print, not a filter.
 */
function filterEntry()
{
    return Entry::query()->where('collection', 'pages')->where('slug', 'testable-html')->first();
}

/**
 * The markdown field renders to:
 *   <p>A wonderful <strong>serenity</strong> has taken
 *   <a href="/de/">possession</a> of my soul, salt &amp; pepper.</p>
 *
 * The `&amp;` is the tell: printing it raw shows `&amp;`, printing it escaped
 * shows `&amp;amp;`. Every assertion below uses that to prove whether a filter
 * result was still treated as HTML.
 */
describe('content-aware filters keep the html marking', function () {
    test('stripTags drops tags and stays html', function () {
        $view = $this->latte('{$page->content|stripTags}', ['page' => filterEntry()]);

        $view->assertDontSee('<strong>', false);
        $view->assertSee('salt &amp; pepper', false);
        $view->assertDontSee('&amp;amp;', false);
    });

    test('trim stays html', function () {
        $this->latte('{$page->content|trim}', ['page' => filterEntry()])
            ->assertSee('<strong>serenity</strong>', false);
    });

    test('spaceless stays html', function () {
        $this->latte('{$page->content|spaceless}', ['page' => filterEntry()])
            ->assertSee('<strong>serenity</strong>', false);
    });

    test('indent stays html', function () {
        $this->latte('{$page->content|indent}', ['page' => filterEntry()])
            ->assertSee('<strong>serenity</strong>', false);
    });

    test('replace stays html', function () {
        $this->latte('{$page->content|replace:"serenity","calm"}', ['page' => filterEntry()])
            ->assertSee('<strong>calm</strong>', false);
    });

    test('repeat stays html', function () {
        $this->latte('{$page->content|repeat:2}', ['page' => filterEntry()])
            ->assertSee('<strong>serenity</strong>', false);
    });

    test('noescape is still accepted on top of them', function () {
        $this->latte('{$page->content|stripTags|noescape}', ['page' => filterEntry()])
            ->assertSee('salt &amp; pepper', false)
            ->assertDontSee('&amp;amp;', false);
    });
});

describe('stripHtml converts to text and is escaped again', function () {
    test('decoded entities are re-escaped on print', function () {
        // convertHtmlToText decodes `&amp;` to `&`; Latte flips the content
        // type to Text so the print escapes it back. Net effect: safe, and
        // visually identical to the raw path.
        $view = $this->latte('{$page->content|stripHtml}', ['page' => filterEntry()]);

        $view->assertDontSee('<strong>', false);
        $view->assertSee('salt &amp; pepper', false);
        $view->assertDontSee('&amp;amp;', false);
    });

    test('user markup inside the field survives the round trip escaped', function () {
        // The bard field holds `<p>A &lt;script&gt; summary</p>`. stripHtml
        // decodes that to a literal `<script>`, so the print MUST escape it.
        $this->latte('{$page->summary|stripHtml}', ['page' => filterEntry()])
            ->assertDontSee('<script>', false)
            ->assertSee('&lt;script&gt;', false);
    });
});

describe('classic filters return plain strings', function () {
    test('upper escapes its result', function () {
        $this->latte('{$page->content|upper}', ['page' => filterEntry()])
            ->assertSee('&lt;STRONG&gt;', false);
    });

    test('truncate escapes its result', function () {
        $this->latte('{$page->content|truncate:20}', ['page' => filterEntry()])
            ->assertSee('&lt;p&gt;', false);
    });

    test('substr escapes its result', function () {
        $this->latte('{$page->content|substr:0,10}', ['page' => filterEntry()])
            ->assertSee('&lt;p&gt;', false);
    });

    test('noescape restores raw output after a classic filter', function () {
        $this->latte('{$page->content|upper|noescape}', ['page' => filterEntry()])
            ->assertSee('<STRONG>SERENITY</STRONG>', false);
    });

    test('breaklines escapes the html it is given, as it does for any value', function () {
        // Stock Latte behavior: breaklines htmlspecialchars() its input before
        // wrapping the result in Html. Same for a {capture} value.
        $this->latte('{$page->content|breaklines}', ['page' => filterEntry()])
            ->assertSee('&lt;p&gt;', false);
    });

    test('length counts the rendered html', function () {
        $entry = filterEntry();
        $html = (string) $entry->augmentedValue('content')->value();

        $this->latte('{$page->content|length}', ['page' => $entry])
            ->assertSee((string) mb_strlen($html));
    });
});

describe('statamic modifiers', function () {
    test('receive the plain string and their output is escaped', function () {
        $this->latte('{$page->content|widont}', ['page' => filterEntry()])
            ->assertSee('&lt;strong&gt;', false);
    });

    test('can be printed raw with noescape', function () {
        $this->latte('{$page->content|widont|noescape}', ['page' => filterEntry()])
            ->assertSee('<strong>serenity</strong>', false);
    });
});

describe('other output contexts', function () {
    test('xml templates print html fields raw, like any Html value', function () {
        // Latte's XML escaper skips HtmlStringable exactly like the HTML one.
        // Worth knowing when writing a feed: use r() below to escape instead.
        $this->latte(
            '{contentType xml}<item><description>{$page->content}</description></item>',
            ['page' => filterEntry()]
        )->assertSee('<strong>serenity</strong>', false);
    });

    test('resolve peels the marking for feeds that need escaped content', function () {
        $this->latte(
            '{contentType xml}<item><description>{r($page->content)}</description></item>',
            ['page' => filterEntry()]
        )->assertSee('&lt;strong&gt;serenity&lt;/strong&gt;', false);
    });

    test('url context sanitizes the value instead of trusting it', function () {
        $this->latte('<a href="{$page->content}">x</a>', ['page' => filterEntry()])
            ->assertDontSee('<strong>', false);
    });
});

describe('parity with latte capture values', function () {
    test('an HtmlValue behaves like a captured Html value', function () {
        $captured = $this->latte(
            '{capture $x}<p>salt &amp; pepper</p>{/capture}{$x|stripTags}'
        )->__toString();

        $marked = $this->latte(
            '{$page->content|stripTags}',
            ['page' => filterEntry()]
        )->__toString();

        expect($captured)->toContain('salt &amp; pepper');
        expect($marked)->toContain('salt &amp; pepper');
    });
});
