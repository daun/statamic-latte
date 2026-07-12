<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\View;
use Latte\Engine;

/**
 * `$this->artisan()` calls straight through `Kernel::call()`, which skips
 * Laravel's Symfony console event rerouting while `app.env` is "testing"
 * (see Illuminate\Foundation\Console\Kernel::__construct()). Force it on so
 * `CommandFinished` — and therefore our listener — actually fires, the same
 * way it would for a real `php artisan view:cache` in production.
 */
function rerouteConsoleEventsForTest(): void
{
    app(Kernel::class)->rerouteSymfonyCommandEvents();
}

describe('warmup on view:cache (opt-in)', function () {
    test('flag on: compiles latte views after view:cache and mentions them in output', function () {
        // Own dedicated fixture directory: Latte caches compiled classes for
        // the lifetime of the PHP process, so reusing a path another test
        // already warmed up would pass even if this listener did nothing.
        View::addLocation(fixtures_path('warmup-view-cache-views'));
        config()->set('statamic-latte.warmup_on_view_cache', true);
        rerouteConsoleEventsForTest();

        $this->artisan('view:cache')
            ->expectsOutputToContain('Latte')
            ->run();

        $engine = $this->app->get(Engine::class);
        $path = realpath(fixtures_path('warmup-view-cache-views/valid.latte'));

        expect(file_exists($engine->getCacheFile($path)))->toBeTrue();
    });

    test('flag off by default: view:cache does not compile latte views', function () {
        View::addLocation(fixtures_path('warmup-view-cache-views-broken'));
        // Not setting the config flag — default is false.
        rerouteConsoleEventsForTest();

        $this->artisan('view:cache')->run();

        $engine = $this->app->get(Engine::class);
        $path = realpath(fixtures_path('warmup-view-cache-views-broken/valid.latte'));

        expect(file_exists($engine->getCacheFile($path)))->toBeFalse();
    });

    test('flag on: a compile error after view:cache fails the process and names the file', function () {
        View::addLocation(fixtures_path('warmup-view-cache-views-broken'));
        config()->set('statamic-latte.warmup_on_view_cache', true);
        rerouteConsoleEventsForTest();

        expect(fn () => $this->artisan('view:cache')->run())
            ->toThrow(RuntimeException::class, 'broken.latte');
    });
});
