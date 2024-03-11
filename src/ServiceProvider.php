<?php

namespace Daun\StatamicLatte;

use Daun\LaravelLatte\Events\LatteEngineCreated;
use Daun\LaravelLatte\Facades\Latte;
use Illuminate\Support\Facades\Event;
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

    protected ?Engine $latte = null;

    public function register(): void
    {
        Event::listen(function (LatteEngineCreated $event) {
            $this->latte = $event->engine;
        });
    }

    public function bootAddon()
    {
        $this->installExtensions();
        $this->registerViewNamespace();
    }

    protected function installExtensions(): void
    {
        $extensions = $this->app['config']->get('latte.statamic.extensions', static::$defaultExtensions);
        foreach ($extensions as $extension) {
            Latte::addExtension(new $extension($this->latte));
        }
    }

    protected function registerViewNamespace(): void
    {
        $namespace = $this->app['config']->get('view.compiled');
        View::addNamespace(static::$temporaryViewNamespace, $namespace);
    }
}
