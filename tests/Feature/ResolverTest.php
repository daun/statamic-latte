<?php

use Daun\StatamicLatte\Data\Resolver;
use Statamic\Fields\ArrayableString;
use Statamic\Fields\LabeledValue;
use Statamic\Fields\Value;

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

test('returns the first non-null value', function () {
    expect(Resolver::actual(null, null, 'third'))->toBe('third');
});

test('returns first argument when all resolve to null', function () {
    expect(Resolver::actual(null))->toBeNull();
});

test('resolve_value helper delegates to the resolver', function () {
    expect(resolve_value(new Value('helper')))->toBe('helper');
});

test('provides resolve() latte function', function () {
    $this->latte('{resolve($val)}', ['val' => new Value('functioned')])
        ->assertSee('functioned');
});

test('provides r() latte function as shorthand', function () {
    $this->latte('{r($val)}', ['val' => new Value('shorthand')])
        ->assertSee('shorthand');
});

test('provides |resolve latte filter', function () {
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

test('drilling returns null for missing keys', function () {
    expect(Resolver::drill(['a' => 1], 'a.b.c'))->toBeNull();
    expect(Resolver::drill(null, 'x'))->toBeNull();
});

test('filter drills into nested keys in a template', function () {
    $this->latte("{\$val|resolve:'author','name'}", ['val' => new Value(['author' => ['name' => 'Drilled']])])
        ->assertSee('Drilled');
});
