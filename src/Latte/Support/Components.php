<?php

namespace Daun\StatamicLatte\Latte\Support;

use Daun\StatamicLatte\Components\Component as LatteComponent;
use Daun\StatamicLatte\Data\Normalizer;
use Illuminate\Support\Str;
use Illuminate\View\AnonymousComponent;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Compilers\ComponentTagCompiler;
use Illuminate\View\ComponentAttributeBag;
use Illuminate\View\ComponentSlot;
use Illuminate\View\FileViewFinder;
use ReflectionClass;
use Throwable;

/**
 * Resolution + runtime helpers for `<x-…>` components. At compile time,
 * a Latte template under `components/` desugars into {embed}+{block} (see
 * ComponentEmbed); anything else dispatches to Blade at runtime via render().
 * A Latte template always wins over a Blade component of the same name.
 */
class Components
{
    public const PREFIX = 'x-';

    public static function prefix(string $name): string
    {
        return self::PREFIX.$name;
    }

    public static function unprefix(string $name): string
    {
        return Str::replaceStart(self::PREFIX, '', $name);
    }

    public static function view(string $name): string
    {
        return 'components.'.$name;
    }

    /**
     * Whether a component resolves to a `.latte` template under `components/`
     * (a `.blade.php` of the same name falls through to the Blade path).
     */
    public static function hasLatteView(string $name): bool
    {
        $relative = 'components/'.str_replace('.', '/', $name).'.latte';
        $finder = app('view')->getFinder();

        if (! $finder instanceof FileViewFinder) {
            return false;
        }

        foreach ($finder->getPaths() as $path) {
            if (is_file(rtrim($path, '/\\').'/'.$relative)) {
                return true;
            }
        }

        return false;
    }

    /** Optional backing class of a Latte component, null for plain templates. */
    public static function latteComponentClass(string $name): ?string
    {
        $class = static::composeClass($name);

        return class_exists($class) && is_subclass_of($class, LatteComponent::class)
            ? $class
            : null;
    }

    /**
     * Data spread into a template component's {embed} args: the attributes,
     * with a backing class's data() merged on top if one exists.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function componentData(string $name, array $attributes): array
    {
        $class = static::latteComponentClass($name);

        if (! $class) {
            return $attributes;
        }

        $component = app()->make($class, $attributes);

        return array_merge($attributes, $component->data());
    }

    /**
     * Render a Blade component by name, with pre-rendered slot strings.
     * Latte template components never reach here — they are desugared to
     * `{embed}` at compile time.
     *
     * @param  array<string, mixed>  $params
     * @param  array<string, ComponentSlot>  $slots
     */
    public static function render(string $name, array $params = [], ?string $slot = null, array $slots = []): string
    {
        // Peel Content/Value wrappers: Blade components don't understand them.
        return static::renderBlade($name, Normalizer::unwrap($params), $slot, $slots);
    }

    /**
     * Resolve a component name to its FQCN. Unlike miko's composeName, this
     * StudlyCases *every* segment (`badge` -> `Badge`) — miko skips names
     * without dash/dot, which breaks PSR-4 on case-sensitive filesystems.
     */
    protected static function composeClass(string $name): string
    {
        if (str_contains($name, '\\')) {
            return $name;
        }

        $class = collect(explode('.', $name))
            ->map(fn (string $segment) => Str::studly($segment))
            ->implode('\\');

        $namespace = rtrim((string) config('latte.components_namespace'), '\\');

        return $namespace.'\\'.$class;
    }

    /**
     * Render a Blade component (class or anonymous). Resolution mirrors
     * Statamic\Tags\ComponentProxy, but the default slot is an already-rendered
     * Latte string echoed straight into the component buffer — never re-parsed.
     *
     * @param  array<string, mixed>  $params
     * @param  array<string, ComponentSlot>  $slots
     */
    protected static function renderBlade(string $name, array $params, ?string $slot, array $slots = []): string
    {
        $obLevel = ob_get_level();

        try {
            $env = view();
            $env->incrementRender();

            $tagCompiler = static::makeComponentTagCompiler();
            $className = $tagCompiler->componentClass($name);

            $data = $params;
            $attributes = new ComponentAttributeBag($data);
            $constructorParameters = [];

            $isAnonymous = false;
            $anonymousViewName = $className;

            if (! class_exists($className)) {
                $isAnonymous = true;
                $className = AnonymousComponent::class;
            }

            if ($constructor = (new ReflectionClass($className))->getConstructor()) {
                $constructorParameters = collect($constructor->getParameters())->map->getName()->all();
                $attributes = $attributes->except($constructorParameters);
                $constructorParameters = collect($data)->only($constructorParameters)->all();
            }

            if ($isAnonymous) {
                $constructorParameters = array_merge(
                    $constructorParameters,
                    $data,
                    ['view' => $anonymousViewName, 'data' => $data],
                );
            }

            $component = $className::resolve($constructorParameters + ((array) $attributes->getIterator()));
            $component->withName($name);
            $env->startComponent($component->resolveView(), $component->data());
            $component->withAttributes($attributes->getAttributes());

            foreach ($slots as $slotName => $componentSlot) {
                $env->slot($slotName, $componentSlot);
            }

            // Echoed pre-rendered, so literal `{{ }}` / `@` from Latte survive.
            if ($slot !== null && $slot !== '') {
                echo $slot;
            }

            $result = $env->renderComponent();

            $env->decrementRender();
            $env->flushStateIfDoneRendering();

            return ltrim($result);
        } catch (Throwable $e) {
            while (ob_get_level() > $obLevel) {
                ob_end_clean();
            }

            throw $e;
        }
    }

    protected static function makeComponentTagCompiler(): ComponentTagCompiler
    {
        /** @var BladeCompiler $blade */
        $blade = app(BladeCompiler::class);

        return new ComponentTagCompiler(
            $blade->getClassComponentAliases(),
            $blade->getClassComponentNamespaces(),
            $blade,
        );
    }
}
