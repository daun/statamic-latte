<?php

namespace Daun\StatamicLatte\Latte\Support;

use Daun\StatamicLatte\Data\Normalizer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\View\AnonymousComponent;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Compilers\ComponentTagCompiler;
use Illuminate\View\ComponentAttributeBag;
use Miko\LaravelLatte\IComponent;
use Miko\LaravelLatte\Runtime\Component;
use ReflectionClass;
use RuntimeException;
use Throwable;

/**
 * Runtime dispatcher for `<x-…>` components in Latte templates.
 *
 * One notation, two destinations, decided at render time:
 *
 *   1. A miko `IComponent` Latte component  → rendered via Component::generate().
 *   2. A Laravel/Statamic Blade component   → rendered via a thin Blade runtime
 *      (class or anonymous), modelled on Statamic's ComponentProxy but echoing
 *      our pre-rendered Latte slot string directly instead of re-parsing it as
 *      Antlers (which would mangle literal `{{ }}` / `@` in the output).
 *
 * When a name resolves to both, the Latte (IComponent) component wins.
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

    /**
     * Render a component by name. Decides Latte vs Blade at runtime.
     *
     * @param  string  $name  The unprefixed component name (e.g. `alert`).
     * @param  array<string, mixed>  $params  Resolved attributes.
     * @param  string|null  $slot  Pre-rendered default-slot string, or null when
     *                             the component was self-closing / had no body.
     */
    public static function render(string $name, array $params = [], ?string $slot = null): string
    {
        $class = static::composeClass($name);

        if (static::isLatteComponent($class)) {
            if ($slot !== null) {
                throw new RuntimeException(
                    "The Latte component <x-{$name}> does not support a slot/body yet."
                );
            }

            // Pass the fully-qualified class name: it contains a backslash, so
            // Component::generate() skips its own (case-lossy) composeName().
            $result = Component::generate($class, $params);

            return $result instanceof View ? $result->render() : (string) $result;
        }

        // Crossing back out of Latte into Blade: peel Content/Value wrappers
        // back to raw Statamic sources, since Blade components don't understand
        // them.
        return static::renderBlade($name, Normalizer::unwrap($params), $slot);
    }

    /**
     * Resolve a component name to its fully-qualified Latte component class.
     *
     * Mirrors miko's Component::composeName (dots become namespace separators)
     * but StudlyCases *every* segment — including single, separator-less names
     * like `badge` -> `Badge`. miko only cases names containing a dash or dot,
     * which works on case-insensitive filesystems (macOS) but breaks PSR-4
     * autoloading on case-sensitive ones (Linux CI), where `...\badge` never
     * resolves to `Badge.php`.
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
     * A composed class maps to a Latte component when it exists and implements
     * the miko IComponent interface. Kept deliberately cheap (class_exists).
     */
    protected static function isLatteComponent(string $class): bool
    {
        return class_exists($class) && is_subclass_of($class, IComponent::class);
    }

    /**
     * Render a Laravel/Statamic Blade component (class or anonymous).
     *
     * Resolution mirrors Statamic\Tags\ComponentProxy (ComponentTagCompiler,
     * AnonymousComponent fallback, ComponentAttributeBag, ctor-param split), but
     * the default slot is supplied as an already-rendered Latte string echoed
     * straight into the component buffer — never re-parsed.
     *
     * @param  array<string, mixed>  $params
     */
    protected static function renderBlade(string $name, array $params, ?string $slot): string
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

            // Decision 1.a: echo the pre-rendered Latte slot directly. It becomes
            // the component's ComponentSlot (rendered raw via {{ $slot }}), so
            // literal `{{ }}` / `@` produced by Latte survive untouched.
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
