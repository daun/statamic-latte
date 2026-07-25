<?php

namespace Tests;

use Daun\StatamicLatte\ServiceProvider as AddonServiceProvider;
use Miko\LaravelLatte\ServiceProvider as LatteServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Statamic\Addons\Manifest;
use Statamic\Facades\Blueprint;
use Statamic\Providers\StatamicServiceProvider;
use Statamic\Statamic;
use Tests\Concerns\InteractsWithLatteViews;
use Tests\Concerns\MocksFrontendRequests;
use Tests\Concerns\ResolvesStatamicConfig;

abstract class TestCase extends OrchestraTestCase
{
    use InteractsWithLatteViews;
    use MocksFrontendRequests;
    use ResolvesStatamicConfig;

    protected function getPackageProviders($app)
    {
        return [
            AddonServiceProvider::class,
            LatteServiceProvider::class,
            StatamicServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app)
    {
        return [
            'Statamic' => Statamic::class,
        ];
    }

    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);

        $app['config']->set('view.paths', [fixtures_path('views')]);

        // Pull in statamic default config, then rewrite content paths to fixtures
        $this->resolveStatamicConfiguration($app);
        $this->resolveStacheStores($app);

        $app['config']->set('statamic.users.repository', 'file');
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Use our fixture blueprints (so entries fields augment relations etc.)
        Blueprint::setDirectory(fixtures_path('blueprints'));
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $this->registerStatamicAddon($app);
    }

    protected function registerStatamicAddon($app)
    {
        $app->make(Manifest::class)->manifest = [
            'daun/statamic-latte' => [
                'id' => 'daun/statamic-latte',
                'namespace' => 'Daun\\StatamicLatte',
            ],
        ];
    }
}
