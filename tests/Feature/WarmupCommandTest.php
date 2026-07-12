<?php

use Daun\StatamicLatte\Latte\Warmup\Warmer;
use Illuminate\Support\Facades\View;
use Latte\Engine;
use Latte\Runtime\Cache;

function warmupCompiledDirectory(): string
{
    return config('latte.compiled') ?? config('view.compiled');
}

describe('latte:warmup', function () {
    test('compiles a valid latte view, exits 0, and reports the count', function () {
        View::addLocation(fixtures_path('warmup-views/valid-only'));

        $this->artisan('latte:warmup')
            ->assertExitCode(0)
            ->expectsOutputToContain('compiled');

        $engine = $this->app->get(Engine::class);
        $path = realpath(fixtures_path('warmup-views/valid-only/valid.latte'));
        $cacheFile = $engine->getCacheFile($path);

        expect(file_exists($cacheFile))->toBeTrue();
    });

    test('exits 1 and names the failing file, while still compiling valid ones', function () {
        View::addLocation(fixtures_path('warmup-views'));

        $this->artisan('latte:warmup')
            ->assertExitCode(1)
            ->expectsOutputToContain('broken.latte')
            ->expectsOutputToContain('failed');

        $engine = $this->app->get(Engine::class);
        $validPath = realpath(fixtures_path('warmup-views/valid.latte'));

        expect(file_exists($engine->getCacheFile($validPath)))->toBeTrue();
    });

    test('compiles a view using a statamic block tag, proving tags are registered at compile time', function () {
        View::addLocation(fixtures_path('warmup-views'));

        $this->artisan('latte:warmup'); // exit code 1 because of broken.latte; not asserted here

        $engine = $this->app->get(Engine::class);
        $path = realpath(fixtures_path('warmup-views/statamic-tag.latte'));
        $cacheFile = $engine->getCacheFile($path);

        expect(file_exists($cacheFile))->toBeTrue();
        expect(file_get_contents($cacheFile))->toContain('Template_');
    });

    test('discovers views registered under a namespace hint', function () {
        View::addNamespace('hinted', fixtures_path('warmup-views-hinted'));

        $warmer = $this->app->make(Warmer::class);
        $discovered = $warmer->discover();

        $hintedPath = realpath(fixtures_path('warmup-views-hinted/hinted.latte'));

        expect($discovered->contains($hintedPath))->toBeTrue();
    });

    test('reports when no latte views are found', function () {
        // Clear both plain paths and namespace hints — the addon's own temp
        // namespace can otherwise still contain leftover extracted `.latte`
        // fragments from previous runs (an implementation detail, not a view).
        View::getFinder()->setPaths([]);
        View::getFinder()->replaceNamespace('laravel-exceptions', []);
        View::getFinder()->replaceNamespace('notifications', []);
        View::getFinder()->replaceNamespace('pagination', []);
        View::getFinder()->replaceNamespace('compiled__views', []);
        View::getFinder()->replaceNamespace('statamic', []);
        View::getFinder()->replaceNamespace('statamic-latte-temp', []);
        View::getFinder()->flush();

        $this->artisan('latte:warmup')
            ->assertExitCode(0)
            ->expectsOutputToContain('No .latte views found');
    });
});

describe('latte:warmup --clear', function () {
    test('removes only previously compiled latte files, not blade output', function () {
        View::addLocation(fixtures_path('warmup-views/valid-only'));

        // Compile a latte view and a blade view so both leave compiled artifacts behind.
        $this->artisan('latte:warmup')->run();
        view('button-blade-component', ['type' => 'submit', 'slot' => 'Go'])->render();

        $directory = warmupCompiledDirectory();
        $before = collect(glob("{$directory}/*"));

        expect($before->count())->toBeGreaterThan(0);

        // Latte's own compiled files, plus the lock files it uses for atomic
        // writes — both are expected to be removed by --clear.
        $isLatteArtifact = fn (string $file) => Cache::isCacheFile($file) || str_ends_with($file, '.lock');

        $latteFilesBefore = $before->filter($isLatteArtifact);
        $otherFilesBefore = $before->reject($isLatteArtifact);

        expect($latteFilesBefore)->not->toBeEmpty();
        expect($otherFilesBefore)->not->toBeEmpty();

        $this->artisan('latte:warmup', ['--clear' => true])->run();

        $after = collect(glob("{$directory}/*"));

        expect($latteFilesBefore->every(fn ($file) => ! $after->contains($file)))->toBeTrue();
        expect($otherFilesBefore->every(fn ($file) => $after->contains($file)))->toBeTrue();
    });
});
