<?php

namespace Tests\Concerns;

trait ResolvesStatamicConfig
{
    protected function resolveStatamicConfiguration($app)
    {
        foreach (glob(statamic_package_path('config/*.php')) as $path) {
            $key = basename($path, '.php');
            $app['config']->set("statamic.{$key}", require $path);
        }
    }

    protected function resolveStacheStores($app)
    {
        $stores = [
            'taxonomies' => 'content/taxonomies',
            'terms' => 'content/terms',
            'collections' => 'content/collections',
            'entries' => 'content/collections',
            'navigation' => 'content/navigation',
            'collection-trees' => 'content/trees/collections',
            'nav-trees' => 'content/trees/navigation',
            'globals' => 'content/globals',
            'global-variables' => 'content/globals',
            'asset-containers' => 'content/assets',
            'users' => 'users',
        ];

        foreach ($stores as $store => $path) {
            $app['config']->set("statamic.stache.stores.{$store}.directory", fixtures_path($path));
        }

        // Wire roles/groups YAML paths to fixture files so the file driver finds them.
        $app['config']->set('statamic.users.repositories.file.paths.roles', fixtures_path('users/roles.yaml'));
        $app['config']->set('statamic.users.repositories.file.paths.groups', fixtures_path('users/groups.yaml'));

        // Register the assets filesystem disk so asset containers can resolve files.
        $app['config']->set('filesystems.disks.assets', [
            'driver' => 'local',
            'root' => fixtures_path('assets-files'),
            'url' => '/assets',
            'visibility' => 'public',
        ]);

        // Disable Glide security tokens in tests to get deterministic URLs.
        $app['config']->set('statamic.assets.image_manipulation.secure', false);
    }
}
