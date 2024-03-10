<?php

use Daun\StatamicLatte\ServiceProvider;

test('installs default extensions', function () {
    /** @var \Latte\Engine $engine */
    $engine = $this->app->get('latte.engine');
    $extensions = collect($engine->getExtensions())->map(fn ($extension) => get_class($extension));
    expect($extensions)->toContain(...ServiceProvider::$defaultExtensions);
});
