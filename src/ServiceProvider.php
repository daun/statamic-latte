<?php

namespace Daun\StatamicLatte;

use Illuminate\Support\Facades\View;
use Latte\Engine;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    public static $defaultExtensions = [
        \Daun\StatamicLatte\Extensions\AntlersExtension::class,
        \Daun\StatamicLatte\Extensions\CacheExtension::class,
        \Daun\StatamicLatte\Extensions\LayoutExtension::class,
        \Daun\StatamicLatte\Extensions\ModifierExtension::class,
        \Daun\StatamicLatte\Extensions\TagExtension::class,
    ];

    public static $temporaryViewNamespace = 'statamic-latte-temp';

    public function bootAddon()
    {
        $this->installExtensions();
        $this->registerViewNamespace();
    }

    protected function installExtensions(): void
    {
        $engine = $this->app->get(Engine::class);
        foreach (static::$defaultExtensions as $extension) {
            $engine->addExtension(new $extension($engine));
        }
    }

    protected function registerViewNamespace(): void
    {
        $namespace = $this->app['config']->get('view.compiled');
        View::addNamespace(static::$temporaryViewNamespace, $namespace);
    }
}
