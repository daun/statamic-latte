<?php

use Daun\StatamicLatte\Data\Content;
use Daun\StatamicLatte\Data\Deferred;
use Statamic\Facades\Entry;

function childPage()
{
    return Entry::query()->where('collection', 'pages')->where('slug', 'testable-child')->first();
}

function plainPage()
{
    return Entry::query()->where('collection', 'pages')->where('slug', 'testable')->first();
}

function isResolved(Deferred $deferred): bool
{
    $prop = (new ReflectionClass($deferred))->getProperty('isResolved');
    $prop->setAccessible(true);

    return $prop->getValue($deferred);
}

describe('deferred wrapping at the render boundary', function () {
    test('defers a non-empty relationship field into a Deferred, unresolved until touched', function () {
        $value = childPage()->augmentedValue('related_pages');
        $deferred = Content::wrapAll(['related' => $value])['related'];

        expect($deferred)->toBeInstanceOf(Deferred::class);
        expect(isResolved($deferred))->toBeFalse();

        // Touching it materializes.
        $first = $deferred[0];
        expect($first->title)->toBe('Testable');
        expect(isResolved($deferred))->toBeTrue();
    });

    test('does not defer an empty relationship field (stays eager and falsy)', function () {
        // testable has no related_pages set.
        $value = plainPage()->augmentedValue('related_pages');
        $wrapped = Content::wrapAll(['related' => $value])['related'];

        expect($wrapped)->not->toBeInstanceOf(Deferred::class);
        expect((bool) $wrapped)->toBeFalse();
    });

    test('does not defer a non-relationship value (markdown content stays eager)', function () {
        $value = plainPage()->augmentedValue('content');
        $wrapped = Content::wrapAll(['content' => $value])['content'];

        expect($wrapped)->not->toBeInstanceOf(Deferred::class);
    });

    test('counts a list relationship by its materialized (iterable) length', function () {
        $value = childPage()->augmentedValue('related_pages');
        $deferred = Content::wrapAll(['related' => $value])['related'];

        expect($deferred)->toBeInstanceOf(Deferred::class);
        // Both target entries are published, so the count matches iteration.
        expect(count($deferred))->toBe(2);
    });

    test('json-encodes to real entry data, not empty objects', function () {
        $value = childPage()->augmentedValue('related_pages');
        $deferred = Content::wrapAll(['related' => $value])['related'];

        $json = json_encode($deferred);
        expect($json)->toContain('Testable');
        expect($json)->not->toBe('[{},{}]');
    });
});

describe('deferred relationships in templates', function () {
    test('empty relationship renders the else branch', function () {
        $this->latte(
            '{if $related}yes{else}no{/if}',
            ['related' => plainPage()->augmentedValue('related_pages')]
        )->assertSee('no');
    });

    test('non-empty relationship is truthy', function () {
        $this->latte(
            '{if $related}yes{else}no{/if}',
            ['related' => childPage()->augmentedValue('related_pages')]
        )->assertSee('yes');
    });

    test('iterates a non-empty list relationship', function () {
        $this->latte(
            '{foreach $related as $r}<li>{$r->title}</li>{/foreach}',
            ['related' => childPage()->augmentedValue('related_pages')]
        )->assertSee('Testable')->assertSee('Testable With Layout');
    });

    test('reports the right length via the modifier boundary', function () {
        $this->latte(
            '{$related|length}',
            ['related' => childPage()->augmentedValue('related_pages')]
        )->assertSee('2');
    });

    test('resolves a single-entry relationship via property access', function () {
        $this->latte(
            '{$author->title}',
            ['author' => childPage()->augmentedValue('related_page')]
        )->assertSee('Testable');
    });

    test('resolves a deferred relationship via the |resolve filter', function () {
        $this->latte(
            '{foreach ($related|resolve) as $r}<li>{$r->title}</li>{/foreach}',
            ['related' => childPage()->augmentedValue('related_pages')]
        )->assertSee('Testable');
    });

    test('crosses back into Antlers with a deferred relationship', function () {
        $this->latte(
            '{antlers}{{ related }}{{ title }}|{{ /related }}{/antlers}',
            ['related' => childPage()->augmentedValue('related_pages')]
        )->assertSee('Testable');
    });
});

describe('deferred method passthrough', function () {
    test('forwards method calls on a single-item deferred relationship', function () {
        $deferred = Content::wrapAll(['related' => childPage()->augmentedValue('related_page')])['related'];

        expect($deferred)->toBeInstanceOf(Deferred::class);
        expect($deferred->slug())->toBe('testable');
    });

    test('rejects method calls on a list deferred relationship', function () {
        $deferred = Content::wrapAll(['related' => childPage()->augmentedValue('related_pages')])['related'];

        expect(fn () => $deferred->slug())->toThrow(BadMethodCallException::class);
    });

    test('blocks destructive methods through the deferred proxy', function () {
        $deferred = Content::wrapAll(['related' => childPage()->augmentedValue('related_page')])['related'];

        expect(fn () => $deferred->delete())->toThrow(LogicException::class);
    });
});
