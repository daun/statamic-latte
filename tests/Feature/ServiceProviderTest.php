<?php

use Daun\StatamicLatte\ServiceProvider;
use Illuminate\Support\Facades\View;
use Latte\Engine;

describe('provider', function () {
    test('adds the addon view namespace', function () {
        $namespaces = View::getFinder()->getHints();
        expect($namespaces)->toHaveKey(ServiceProvider::$temporaryViewNamespace);
    });

    test('installs the default extensions', function () {
        $engine = $this->app->get(Engine::class);
        $extensions = collect($engine->getExtensions())->map(fn ($extension) => get_class($extension));
        expect($extensions)->toContain(...ServiceProvider::$defaultExtensions);
    });
});
