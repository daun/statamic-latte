<?php

namespace Tests;

use Daun\LaravelLatte\ServiceProvider as LatteServiceProvider;
use Daun\StatamicLatte\ServiceProvider as AddonServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Statamic\Extend\Manifest;
use Statamic\Providers\StatamicServiceProvider;
use Statamic\Statamic;
use Tests\Concerns\InteractsWithLatteViews;
use Tests\Concerns\ResolvesStatamicConfig;

abstract class TestCase extends OrchestraTestCase
{
    use InteractsWithLatteViews;
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

        // Custom view directory
        $app['config']->set('view.paths', [fixtures_path('views')]);

        // Pull in statamic default config
        $this->resolveStatamicConfiguration($app);

        // Rewrite content paths to use our test fixtures
        $this->resolveStacheStores($app);

        // Create two sites for multi-site testing
        $app['config']->set('statamic.sites.sites', [
            'default' => ['name' => 'English', 'locale' => 'en_US', 'url' => '/'],
            'german' => ['name' => 'Deutsch', 'locale' => 'de_DE', 'url' => '/de/'],
        ]);

        // Set user repository to default flat file system
        $app['config']->set('statamic.users.repository', 'file');

        // Assume pro edition for our tests
        // $app['config']->set('statamic.editions.pro', true);
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
