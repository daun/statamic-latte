<?php

namespace Daun\StatamicLatte;

use Daun\StatamicLatte\Latte\Extensions;
use Illuminate\Support\Facades\View;
use Latte\Engine;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    public static $defaultExtensions = [
        Extensions\AntlersExtension::class,
        Extensions\CacheExtension::class,
        Extensions\LayoutExtension::class,
        Extensions\ModifierExtension::class,
        Extensions\TagExtension::class,
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
        $namespace = $this->app->get('config')->get('view.compiled');
        View::addNamespace(static::$temporaryViewNamespace, $namespace);
    }
}
