<?php

namespace Tests;

use Daun\LaravelLatte\ServiceProvider as LatteServiceProvider;
use Daun\StatamicLatte\ServiceProvider as AddonServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Statamic\Extend\Manifest;
use Statamic\Providers\StatamicServiceProvider;
use Statamic\Statamic;
use Tests\Concerns\InteractsWithLatteViews;
use Tests\Concerns\ModifiesConfig;

abstract class TestCase extends OrchestraTestCase
{
    use InteractsWithLatteViews;
    use ModifiesConfig;

    protected function getPackageProviders($app)
    {
        return [
            StatamicServiceProvider::class,
            AddonServiceProvider::class,
            LatteServiceProvider::class,
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

        $configs = [
            'assets',
            'cp',
            'forms',
            'git',
            'routes',
            'sites',
            'stache',
            'static_caching',
            'system',
            'users',
        ];

        foreach ($configs as $config) {
            $values = require __DIR__."/../vendor/statamic/cms/config/{$config}.php";
            $app['config']->set("statamic.{$config}", $values);
        }

        // Creat two site for multi site testing
        $app['config']->set('statamic.sites.sites', [
            'default' => ['name' => 'English', 'locale' => 'en_US', 'url' => '/'],
            'german' => ['name' => 'Deutsch', 'locale' => 'de_DE', 'url' => '/de/'],
        ]);

        // Setting the user repository to the default flat file system
        $app['config']->set('statamic.users.repository', 'file');

        // Set the content paths for our stache stores
        $app['config']->set('statamic.stache.stores.taxonomies.directory', __DIR__.'/__fixtures__/content/taxonomies');
        $app['config']->set('statamic.stache.stores.terms.directory', __DIR__.'/__fixtures__/content/taxonomies');
        $app['config']->set('statamic.stache.stores.collections.directory', __DIR__.'/__fixtures__/content/collections');
        $app['config']->set('statamic.stache.stores.entries.directory', __DIR__.'/__fixtures__/content/collections');
        $app['config']->set('statamic.stache.stores.navigation.directory', __DIR__.'/__fixtures__/content/navigation');
        $app['config']->set('statamic.stache.stores.collection-trees.directory', __DIR__.'/__fixtures__/content/trees/collections');
        $app['config']->set('statamic.stache.stores.nav-trees.directory', __DIR__.'/__fixtures__/content/trees/navigation');
        $app['config']->set('statamic.stache.stores.globals.directory', __DIR__.'/__fixtures__/content/globals');
        $app['config']->set('statamic.stache.stores.asset-containers.directory', __DIR__.'/__fixtures__/content/assets');
        $app['config']->set('statamic.stache.stores.users.directory', __DIR__.'/__fixtures__/users');

        // Assume the pro edition for our tests
        // $app['config']->set('statamic.editions.pro', true);

        // Custom view directory
        $app['config']->set('view.paths', [__DIR__.'/fixtures/views']);
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app->make(Manifest::class)->manifest = [
            'daun/statamic-latte' => [
                'id' => 'daun/statamic-latte',
                'namespace' => 'Daun\\StatamicLatte',
            ],
        ];
    }
}
