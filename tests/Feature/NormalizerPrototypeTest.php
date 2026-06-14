<?php

use Daun\StatamicLatte\Data\Content;
use Daun\StatamicLatte\Data\Normalizer;
use Statamic\Facades\Entry;

function testEntry()
{
    return Entry::query()->where('collection', 'pages')->where('slug', 'testable')->first();
}

test('normalizes a single entry into a Content object', function () {
    $entry = testEntry();
    expect($entry)->not->toBeNull();

    $content = Normalizer::normalize($entry);
    expect($content)->toBeInstanceOf(Content::class);
});

test('lazily augments only accessed keys via property syntax', function () {
    $content = Normalizer::normalize(testEntry());

    expect($content->title)->toBe('Testable');
    expect((string) $content->slug)->toBe('testable');
});

test('supports both -> and [] access', function () {
    $content = Normalizer::normalize(testEntry());

    expect($content->title)->toBe($content['title']);
});

test('isset works for missing and present keys', function () {
    $content = Normalizer::normalize(testEntry());

    expect(isset($content->title))->toBeTrue();
    expect(isset($content->nonexistent_field_xyz))->toBeFalse();
});

test('a query result normalizes to a plain array of Content objects', function () {
    $results = Normalizer::normalize(Entry::query()->where('collection', 'pages')->get());

    expect($results)->toBeArray();
    expect($results)->each->toBeInstanceOf(Content::class);
});

test('renders entry fields via -> in a real Latte template', function () {
    $this->latte('<h1>{$page->title}</h1>', ['page' => testEntry()])
        ->assertSee('Testable');
});

test('iterates a query result as a plain array in Latte', function () {
    $this->latte(
        '{foreach $pages as $p}<li>{$p->title}</li>{/foreach}',
        ['pages' => Entry::query()->where('collection', 'pages')->get()]
    )->assertSee('Testable');
});

test('nested entries-field relation chains Content -> Content', function () {
    $child = Entry::query()->where('collection', 'pages')->where('slug', 'testable-child')->first();
    $content = Normalizer::normalize($child);

    expect($content->title)->toBe('Testable Child');
    // related_page is an entries field (max_items: 1) -> single related Entry
    $related = $content->related_page;
    expect($related)->toBeInstanceOf(Content::class);
    expect($related->title)->toBe('Testable');
});

test('renders a nested relation field via -> in Latte', function () {
    $child = Entry::query()->where('collection', 'pages')->where('slug', 'testable-child')->first();
    $this->latte('{$page->related_page->title}', ['page' => $child])
        ->assertSee('Testable');
});

test('s() tag results are normalized to Content objects', function () {
    $this->latte(
        "{foreach s('collection:pages') as \$entry}<li>{\$entry->title}</li>{/foreach}",
        []
    )->assertSee('Testable');
});

test('Content is unwrapped before reaching a modifier', function () {
    // length modifier on a keyed Content -> operates on the raw array (3 keys)
    $this->latte('{$opts|length}', ['opts' => ['a' => 1, 'b' => 2, 'c' => 3]])
        ->assertSee('3');
});

test('unwraps Content back into Antlers (reverse boundary)', function () {
    $this->latte('{antlers}{{ page:title }}{/antlers}', ['page' => testEntry()])
        ->assertSee('Testable');
});

function nestedEntry()
{
    return Entry::query()->where('collection', 'pages')->where('slug', 'testable-nested')->first();
}

test('forward: nested group object + grid array of objects resolve via ->', function () {
    $page = Normalizer::normalize(nestedEntry());

    // group -> Content (keyed object)
    expect($page->meta)->toBeInstanceOf(Content::class);
    expect($page->meta->author)->toBe('Jane');
    // entries field nested inside the group -> Content
    expect($page->meta->featured_page)->toBeInstanceOf(Content::class);
    expect($page->meta->featured_page->title)->toBe('Testable');
    // grid -> plain array of Content rows
    expect($page->blocks)->toBeArray();
    expect($page->blocks[0])->toBeInstanceOf(Content::class);
    expect($page->blocks[0]->heading)->toBe('First Block');
});

test('reverse: deeply nested data unwraps correctly back into Antlers', function () {
    $template = <<<'LATTE'
        {antlers}
            author={{ page:meta:author }}
            featured={{ page:meta:featured_page:title }}
            blocks={{ page:blocks }}{{ heading }}|{{ /page:blocks }}
        {/antlers}
    LATTE;

    $this->latte($template, ['page' => nestedEntry()])
        ->assertSee('author=Jane')
        ->assertSee('featured=Testable')
        ->assertSee('blocks=First Block|Second Block|');
});

test('a single keyed Content tag result is exposed as $value, not looped', function () {
    // {s:..} over an object-shaped result should not iterate its fields.
    $content = Normalizer::normalize(['title' => 'Solo', 'body' => 'B']);
    expect($content)->toBeInstanceOf(Content::class);
    // Mirrors TagNode's loop guard: Content is excluded from iteration.
    expect(is_iterable($content) && ! $content instanceof Content)->toBeFalse();
});

test('list tag results still loop (lists are plain arrays)', function () {
    $this->latte(
        "{s:collection:pages}<li>{\$value->title}</li>{/s:collection:pages}",
        []
    )->assertSee('Testable');
});

test('lazy: accessing one field does not augment the whole entry', function () {
    $entry = testEntry();
    $content = Normalizer::normalize($entry);

    // Touch only title.
    $content->title;

    $cache = (new ReflectionClass($content))->getProperty('cache');
    $cache->setAccessible(true);

    expect(array_keys($cache->getValue($content)))->toBe(['title']);
});
