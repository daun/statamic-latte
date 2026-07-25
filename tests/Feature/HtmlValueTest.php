<?php

use Daun\StatamicLatte\Data\Content;
use Daun\StatamicLatte\Data\HtmlValue;
use Statamic\Facades\Entry;

function htmlEntry()
{
    return Entry::query()->where('collection', 'pages')->where('slug', 'testable-html')->first();
}

describe('html fields', function () {
    test('marks a markdown field as rendered html', function () {
        $content = Content::wrap(htmlEntry());

        expect($content->content)->toBeInstanceOf(HtmlValue::class);
        expect((string) $content->content)->toContain('<strong>serenity</strong>');
    });

    test('prints a markdown field unescaped, without noescape', function () {
        $this->latte('{$page->content}', ['page' => htmlEntry()])
            ->assertSee('<strong>serenity</strong>', false);
    });

    test('still accepts an explicit noescape', function () {
        $this->latte('{$page->content|noescape}', ['page' => htmlEntry()])
            ->assertSee('<strong>serenity</strong>', false);
    });

    test('marks a bard field without sets as rendered html', function () {
        $this->latte('{$page->summary}', ['page' => htmlEntry()])
            ->assertSee('<p>A &lt;script&gt; summary</p>', false);
    });

    test('escapes text fields even when they contain markup', function () {
        $this->latte('{$page->title}', ['page' => htmlEntry()])
            ->assertSee('Testable &lt;b&gt;Html&lt;/b&gt;', false);
    });

    test('keeps escaping user markup embedded in an html field', function () {
        // The `<script>` was typed into the field, so Statamic's own rendering
        // encoded it. Trusting the field must not decode it again.
        $this->latte('{$page->summary}', ['page' => htmlEntry()])
            ->assertDontSee('<script>', false);
    });

    test('escapes html values in attribute context', function () {
        $this->latte('<div title="{$page->summary}"></div>', ['page' => htmlEntry()])
            ->assertDontSee('<p>', false);
    });

    test('leaves an empty html field falsy', function () {
        $blank = Entry::query()->where('collection', 'pages')->where('slug', 'testable')->first();

        expect(Content::wrap($blank)->summary)->not->toBeInstanceOf(HtmlValue::class);

        $this->latte('{if $page->summary}yes{else}no{/if}', ['page' => $blank])
            ->assertSee('no');
    });
});

describe('html fields in bard sets', function () {
    test('marks bard text sets as rendered html', function () {
        $this->latte(
            '{foreach $page->story as $set}{if $set->type === "text"}{$set->text}{/if}{/foreach}',
            ['page' => htmlEntry()]
        )->assertSee('<p>Before the set</p>', false);
    });

    test('escapes a text field of a blueprint set named text', function () {
        $this->latte(
            '{foreach $page->story as $set}{if $set->type === "quote"}{$set->text}{/if}{/foreach}',
            ['page' => htmlEntry()]
        )->assertSee('Quoted &lt;b&gt;text&lt;/b&gt;', false);
    });

    test('marks a markdown field nested in a bard set', function () {
        $this->latte(
            '{foreach $page->story as $set}{if $set->type === "quote"}{$set->note}{/if}{/foreach}',
            ['page' => htmlEntry()]
        )->assertSee('<em>noted</em>', false);
    });
});

describe('html fields in grid rows', function () {
    test('marks a markdown field nested in a grid row', function () {
        $this->latte(
            '{foreach $page->blocks as $block}{$block->body}{/foreach}',
            ['page' => htmlEntry()]
        )->assertSee('<strong>grid</strong>', false);
    });

    test('escapes the text fields of a grid row', function () {
        $this->latte(
            '{foreach $page->blocks as $block}{$block->heading}{/foreach}',
            ['page' => htmlEntry()]
        )->assertSee('Block &lt;b&gt;heading&lt;/b&gt;', false);
    });
});

describe('html values at other boundaries', function () {
    test('hands modifiers the plain string', function () {
        $this->latte('{$page->content|strip_tags|truncate:12}', ['page' => htmlEntry()])
            ->assertSee('A wonderful');
    });

    test('unwraps to the plain string', function () {
        $content = Content::wrap(htmlEntry());

        expect(Content::unwrap($content->content))->toBeString();
    });

    test('resolves to the plain string', function () {
        $this->latte('{r($page->content)}', ['page' => htmlEntry()])
            ->assertSee('&lt;strong&gt;serenity&lt;/strong&gt;', false);
    });

    test('honors an emptied fieldtype list', function () {
        $original = HtmlValue::$fieldtypes;
        HtmlValue::$fieldtypes = [];

        try {
            $this->latte('{$page->content}', ['page' => htmlEntry()])
                ->assertSee('&lt;strong&gt;serenity&lt;/strong&gt;', false);
        } finally {
            HtmlValue::$fieldtypes = $original;
        }
    });
});
