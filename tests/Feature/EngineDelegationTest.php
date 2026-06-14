<?php

use Daun\StatamicLatte\Latte\NormalizingEngine;
use Illuminate\Support\Facades\View;
use Miko\LaravelLatte\DeterministicKeys;
use Miko\LaravelLatte\LatteEngine;

describe('engine', function () {
    test('extends the Miko LatteEngine', function () {
        $engine = View::getEngineResolver()->resolve('latte');

        expect($engine)->toBeInstanceOf(NormalizingEngine::class);
        // It IS a laravel-latte engine, so all of its behaviour is inherited.
        expect($engine)->toBeInstanceOf(LatteEngine::class);
    });
});

describe('delegation', function () {
    test('applies the nl2br filter through the normalizing engine', function () {
        $this->latte('{$text|nl2br}', ['text' => "line1\nline2"], squish: false)
            ->assertSee('line1<br', false)
            ->assertSee('line2', false);
    });

    test('resolves {link this} to the current url', function () {
        $this->latte('{link this}')
            ->assertSee('http', false);
    });

    test('generates the n:href attribute', function () {
        $this->latte('<a n:href="this">home</a>')
            ->assertSee('href=', false)
            ->assertSee('home', false);
    });

    test('preserves deterministic Livewire keys', function () {
        // A render must have set the compiled path on the shared instance;
        // if delegation were broken, setPath would never be called and
        // generate() would throw "Latest compiled path not found.".
        $this->latte('{$x}', ['x' => 'ok'])->assertSee('ok');

        expect(DeterministicKeys::generate('lw'))->toStartWith('lw-');
    });
});
