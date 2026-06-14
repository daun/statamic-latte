<?php

use Daun\StatamicLatte\Latte\NormalizingEngine;
use Illuminate\Support\Facades\View;
use Miko\LaravelLatte\DeterministicKeys;
use Miko\LaravelLatte\LatteEngine;

test('the registered latte engine extends Miko LatteEngine', function () {
    $engine = View::getEngineResolver()->resolve('latte');

    expect($engine)->toBeInstanceOf(NormalizingEngine::class);
    // It IS a laravel-latte engine, so all of its behaviour is inherited.
    expect($engine)->toBeInstanceOf(LatteEngine::class);
});

describe('laravel-latte features still work through the normalizing engine', function () {
    test('nl2br filter (registered by Miko\\LaravelLatte\\Extension)', function () {
        $this->latte('{$text|nl2br}', ['text' => "line1\nline2"], squish: false)
            ->assertSee('line1<br', false)
            ->assertSee('line2', false);
    });

    test('{link this} resolves to the current url', function () {
        $this->latte('{link this}')
            ->assertSee('http', false);
    });

    test('n:href attribute is generated', function () {
        $this->latte('<a n:href="this">home</a>')
            ->assertSee('href=', false)
            ->assertSee('home', false);
    });

    test('deterministic Livewire keys flow through delegation', function () {
        // A render must have set the compiled path on the shared instance;
        // if delegation were broken, setPath would never be called and
        // generate() would throw "Latest compiled path not found.".
        $this->latte('{$x}', ['x' => 'ok'])->assertSee('ok');

        expect(DeterministicKeys::generate('lw'))->toStartWith('lw-');
    });
});
