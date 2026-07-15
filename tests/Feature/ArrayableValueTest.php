<?php

use Daun\StatamicLatte\Data\ArrayableValue;
use Daun\StatamicLatte\Data\Content;
use Daun\StatamicLatte\Data\Resolver;
use Statamic\Dictionaries\Item;
use Statamic\Facades\Asset;
use Statamic\Facades\Entry;
use Statamic\Fields\ArrayableString;
use Statamic\Fields\Field;
use Statamic\Fields\LabeledValue;
use Statamic\Fields\Value;
use Statamic\Fields\Values;
use Statamic\Fieldtypes\Link\ArrayableLink;

function arrayableValueEntry()
{
    return Entry::query()->where('collection', 'pages')->where('slug', 'testable')->first();
}

describe('arrayable value proxy', function () {
    test('exposes labeled values through object and array syntax', function () {
        $value = Content::wrap(new LabeledValue('news', 'News'));

        expect($value)->toBeInstanceOf(ArrayableValue::class)
            ->and((string) $value)->toBe('news')
            ->and($value->value)->toBe('news')
            ->and($value->key)->toBe('news')
            ->and($value->label)->toBe('News')
            ->and($value['label'])->toBe('News')
            ->and(isset($value->label))->toBeTrue()
            ->and(isset($value->missing))->toBeFalse();
    });

    test('exposes code field metadata through object syntax', function () {
        $value = Content::wrap(new ArrayableString(
            '<h1>Hello</h1>',
            ['code' => '<h1>Hello</h1>', 'mode' => 'html'],
        ));

        expect($value)->toBeInstanceOf(ArrayableValue::class)
            ->and((string) $value)->toBe('<h1>Hello</h1>')
            ->and($value->code)->toBe('<h1>Hello</h1>')
            ->and($value->mode)->toBe('html');
    });

    test('exposes dictionary item metadata through object syntax', function () {
        $value = Content::wrap(new Item('AT', 'Austria', [
            'iso2' => 'AT',
            'region' => ['name' => 'Europe'],
        ]));

        expect($value)->toBeInstanceOf(ArrayableValue::class)
            ->and((string) $value)->toBe('AT')
            ->and($value->label)->toBe('Austria')
            ->and($value->iso2)->toBe('AT')
            ->and($value->region)->toBeInstanceOf(Content::class)
            ->and($value->region->name)->toBe('Europe');
    });

    test('preserves a Link field inside an augmented Values row', function () {
        $entry = arrayableValueEntry();
        expect($entry)->not->toBeNull();

        $field = new Field('link', ['type' => 'link']);
        $row = new Values([
            'link' => new Value('entry::'.$entry->id(), 'link', $field->fieldtype(), $entry),
        ]);

        $this->latte(
            '{$row->link}|{$row->link->url}|{$row->link->title}',
            ['row' => $row],
        )->assertSee('/testable|/testable|Testable', false);
    });

    test('renders structured string values through Latte object syntax', function () {
        $this->latte(
            '{$row->choice}|{$row->choice->label}|{$row->code->mode}',
            ['row' => [
                'choice' => new LabeledValue('news', 'News'),
                'code' => new ArrayableString('<h1>Hello</h1>', ['mode' => 'html']),
            ]],
        )->assertSee('news|News|html', false);
    });

    test('exposes linked asset metadata through object syntax', function () {
        $asset = Asset::find('assets::img/example.jpg');
        expect($asset)->not->toBeNull();

        $value = Content::wrap(new ArrayableLink($asset));

        expect($value)->toBeInstanceOf(ArrayableValue::class)
            ->and((string) $value)->toBe('/assets/img/example.jpg')
            ->and($value->url)->toBe('/assets/img/example.jpg')
            ->and($value->width)->toBe(20)
            ->and($value->height)->toBe(10);
    });

    test('exposes only a URL for plain links', function () {
        $value = Content::wrap(new ArrayableLink('https://example.com'));

        expect($value)->toBeInstanceOf(ArrayableValue::class)
            ->and((string) $value)->toBe('https://example.com')
            ->and($value->url)->toBe('https://example.com')
            ->and($value->title)->toBeNull()
            ->and(isset($value->title))->toBeFalse();
    });

    test('preserves linked objects without a URL', function () {
        $target = new class
        {
            public function url(): mixed
            {
                return null;
            }

            public function absoluteUrl(): mixed
            {
                return null;
            }

            public function toAugmentedArray(): array
            {
                return ['title' => 'Unrouted', 'url' => null];
            }

            public function toShallowAugmentedArray(): array
            {
                return $this->toAugmentedArray();
            }
        };

        $value = Content::wrap(new ArrayableLink($target));

        expect($value)->toBeInstanceOf(ArrayableValue::class)
            ->and((string) $value)->toBe('')
            ->and($value->title)->toBe('Unrouted');
    });

    test('forwards source methods and wraps their return values', function () {
        $entry = arrayableValueEntry();
        $value = Content::wrap(new ArrayableLink($entry));

        expect($value->url())->toBe('/testable')
            ->and($value->value())->toBeInstanceOf(Content::class)
            ->and($value->value()->title)->toBe('Testable')
            ->and(fn () => $value->missingMethod())
            ->toThrow(BadMethodCallException::class);
    });

    test('keeps falsy values scalar for correct Latte truthiness', function () {
        expect(Content::wrap(new ArrayableString('')))->toBe('')
            ->and(Content::wrap(new ArrayableString('0')))->toBe('0')
            ->and(Content::wrap(new LabeledValue(0, 'Zero')))->toBe(0)
            ->and(Content::wrap(new LabeledValue(false, 'No')))->toBeFalse();

        $this->latte(
            '{if $empty}wrong{else}empty{/if}|{if $zero}wrong{else}zero{/if}',
            ['empty' => new ArrayableString(''), 'zero' => new LabeledValue(0, 'Zero')],
        )->assertSee('empty|zero');
    });

    test('serializes like the original Statamic values', function () {
        $sources = [
            new LabeledValue('news', 'News'),
            new ArrayableLink(arrayableValueEntry()),
        ];

        foreach ($sources as $source) {
            expect(json_encode(Content::wrap($source)))->toBe(json_encode($source));
        }

        expect(json_encode(Content::wrap(new LabeledValue(null, 'None'))))->toBe('null');
    });

    test('unwraps and resolves to the previous scalar shape', function () {
        $link = Content::wrap(new ArrayableLink('https://example.com'));
        $choice = Content::wrap(new LabeledValue(7, 'Seven'));

        expect(Content::unwrap($link))->toBe('https://example.com')
            ->and(Resolver::actual($link))->toBe('https://example.com')
            ->and(Content::unwrap($choice))->toBe(7)
            ->and(Resolver::actual($choice))->toBe(7);

        $this->latte('{$choice|upper}', ['choice' => new LabeledValue('news', 'News')])
            ->assertSee('NEWS');
    });

    test('is read-only', function () {
        $value = Content::wrap(new LabeledValue('news', 'News'));

        expect(fn () => $value['label'] = 'Changed')
            ->toThrow(LogicException::class);

        expect(function () use ($value) {
            unset($value['label']);
        })->toThrow(LogicException::class);

        expect(fn () => $value->label = 'Changed')
            ->toThrow(LogicException::class);

        expect(function () use ($value) {
            unset($value->label);
        })->toThrow(LogicException::class);
    });
});
