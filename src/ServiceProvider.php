<?php

namespace Daun\StatamicLatte;

use Daun\StatamicLatte\Latte\BladeStyleLoader;
use Daun\StatamicLatte\Latte\Extensions;
use Daun\StatamicLatte\Latte\Loaders\TagMethodLoader;
use Daun\StatamicLatte\Latte\NormalizingEngine;
use Illuminate\Support\Facades\View;
use Latte\Engine;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    public static $defaultExtensions = [
        Extensions\AntlersExtension::class,
        Extensions\AttributeNormalizationExtension::class,
        Extensions\CacheExtension::class,
        Extensions\LayoutExtension::class,
        Extensions\ModifierExtension::class,
        Extensions\ResolverExtension::class,
        Extensions\SectionExtension::class,
        Extensions\SlotExtension::class,
        Extensions\TagExtension::class,
    ];

    public static $temporaryViewNamespace = 'statamic-latte-temp';

    public function bootAddon()
    {
        $this->installLoader();
        $this->installExtensions();
        $this->installEngine();
        $this->registerViewNamespace();
    }

    protected function installEngine(): void
    {
        // Override Miko's 'latte' engine with one that normalizes Statamic
        // data into Content objects + plain arrays at the render boundary.
        // Deferred via booted() so it wins over Miko's own registration.
        $this->app->booted(function () {
            $factory = $this->app->get('view');
            $factory->addExtension('latte', 'latte', function () {
                return new NormalizingEngine($this->app->get(Engine::class));
            });
        });
    }

    protected function installLoader(): void
    {
        $view = $this->app->get('view');
        $engine = $this->app->get(Engine::class);
        $loader = new TagMethodLoader(new BladeStyleLoader($view));
        $engine->setLoader($loader);
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
