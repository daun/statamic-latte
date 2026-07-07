<?php

use Daun\StatamicLatte\Data\Resolver;
use Statamic\Fields\ArrayableString;
use Statamic\Fields\LabeledValue;
use Statamic\Fields\Value;
use Statamic\Statamic;
use Statamic\Tags\FluentTag;

describe('resolver', function () {
    test('returns plain scalars untouched', function () {
        expect(Resolver::actual('hello'))->toBe('hello');
        expect(Resolver::actual(42))->toBe(42);
        expect(Resolver::actual(['a', 'b']))->toBe(['a', 'b']);
    });

    test('unwraps a Value object', function () {
        expect(Resolver::actual(new Value('foo')))->toBe('foo');
    });

    test('unwraps a LabeledValue object', function () {
        expect(Resolver::actual(new LabeledValue('foo', 'Foo Label')))->toBe('foo');
    });

    test('stringifies an ArrayableString', function () {
        expect(Resolver::actual(new ArrayableString('bar', [])))->toBe('bar');
    });

    test('loops until stable when one unwrap exposes another wrapper', function () {
        // A Value whose augmented value is itself an ArrayableString: Statamic's
        // single-pass helper would return the ArrayableString; the loop peels it.
        expect(Resolver::actual(new Value(fn () => new ArrayableString('x', []))))->toBe('x');
    });

    test('resolves a Modify chain to its final value', function () {
        // statamic_value() recurses on Modify->fetch(); exercises that path.
        expect(Resolver::actual(Statamic::modify('hello')->upper()))->toBe('HELLO');
    });

    test('resolves a FluentTag to its fetched output', function () {
        // FluentTag shares the same fetch-recursion path as Modify: the tag is
        // unwrapped to its fetched result (an entries collection), not left wrapped.
        $result = Resolver::actual(Statamic::tag('collection:pages'));
        expect($result)->not->toBeInstanceOf(FluentTag::class);
        expect(count($result))->toBeGreaterThan(0);
    });

    test('returns the first non-null value', function () {
        expect(Resolver::actual(null, null, 'third'))->toBe('third');
    });

    test('returns the first argument when all resolve to null', function () {
        expect(Resolver::actual(null))->toBeNull();
    });

    test('delegates resolve_value to the resolver', function () {
        expect(resolve_value(new Value('helper')))->toBe('helper');
    });

    test('provides the resolve() latte function', function () {
        $this->latte('{resolve($val)}', ['val' => new Value('functioned')])
            ->assertSee('functioned');
    });

    test('provides the r() latte function as shorthand', function () {
        $this->latte('{r($val)}', ['val' => new Value('shorthand')])
            ->assertSee('shorthand');
    });

    test('provides the |resolve latte filter', function () {
        $this->latte('{$val|resolve}', ['val' => new Value('filtered')])
            ->assertSee('filtered');
    });

    test('drills into nested keys via the filter', function () {
        expect(Resolver::drill(['author' => ['name' => 'Jane']], 'author', 'name'))->toBe('Jane');
        expect(Resolver::drill(['author' => ['name' => 'Jane']], 'author.name'))->toBe('Jane');
    });

    test('drills through wrapped values at each step', function () {
        $data = new Value(['author' => new Value(['name' => new Value('Wrapped')])]);
        expect(Resolver::drill($data, 'author.name'))->toBe('Wrapped');
    });

    test('returns null for missing keys when drilling', function () {
        expect(Resolver::drill(['a' => 1], 'a.b.c'))->toBeNull();
        expect(Resolver::drill(null, 'x'))->toBeNull();
    });

    test('drills into nested keys via the filter in a template', function () {
        $this->latte("{\$val|resolve:'author','name'}", ['val' => new Value(['author' => ['name' => 'Drilled']])])
            ->assertSee('Drilled');
    });
});
