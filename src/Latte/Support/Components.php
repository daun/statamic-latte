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
 * Resolution + runtime helpers for `<x-…>` components in Latte templates.
 *
 * One notation, two destinations, decided at COMPILE time by ComponentExtension:
 *
 *   1. A Latte component template `components/<name>.latte`  → desugared into a
 *      native `{embed}` + `{block}` subtree (see ComponentEmbed). Slots map to
 *      blocks, so default-slot fallback and caller-scoped slot content come for
 *      free. An optional backing {@see LatteComponent} class supplies extra data.
 *   2. Otherwise a Laravel/Statamic Blade component  → a runtime ComponentNode
 *      dispatch via {@see render()}, echoing our pre-rendered Latte slot string
 *      directly instead of re-parsing it as Antlers (which would mangle literal
 *      `{{ }}` / `@` in the output).
 *
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

    /**
     * The view name of a component's Latte template, e.g. `alert` -> `components.alert`
     * and `forms.field` -> `components.forms.field`.
     */
    public static function view(string $name): string
    {
        return 'components.'.$name;
    }

    /**
     * Whether a component resolves to a Latte template under `components/`.
     * Checked against the finder's paths so we only match `.latte` files
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

    /**
     * Resolve a component name to its optional backing Latte component class,
     * or null when the component is a plain (anonymous) template.
     */
    public static function latteComponentClass(string $name): ?string
    {
        $class = static::composeClass($name);

        return class_exists($class) && is_subclass_of($class, LatteComponent::class)
            ? $class
            : null;
    }

    /**
     * Build the data spread into a template component's {embed} args at runtime.
     * With a backing class: constructor filled from attributes (via container),
     * its data() merged over the raw attributes. Without one: the attributes.
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
     * Render a Blade component by name (runtime fallback for `<x-…>` tags that
     * don't resolve to a Latte template). Latte template components never reach
     * here — they are desugared to `{embed}` at compile time.
     *
     * @param  string  $name  The unprefixed component name (e.g. `alert`).
     * @param  array<string, mixed>  $params  Resolved attributes.
     * @param  string|null  $slot  Pre-rendered default-slot string, or null when
     *                             the component was self-closing / had no body.
     * @param  array<string, ComponentSlot>  $slots  Pre-rendered
     *                                               named slots, keyed by name.
     */
    public static function render(string $name, array $params = [], ?string $slot = null, array $slots = []): string
    {
        // Crossing back out of Latte into Blade: peel Content/Value wrappers
        // back to raw Statamic sources, since Blade components don't understand
        // them.
        return static::renderBlade($name, Normalizer::unwrap($params), $slot, $slots);
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
     * Render a Laravel/Statamic Blade component (class or anonymous).
     *
     * Resolution mirrors Statamic\Tags\ComponentProxy (ComponentTagCompiler,
     * AnonymousComponent fallback, ComponentAttributeBag, ctor-param split), but
     * the default slot is supplied as an already-rendered Latte string echoed
     * straight into the component buffer — never re-parsed.
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

            // Named slots become $header, $footer, … (as ComponentSlot objects),
            // pre-rendered by Latte and passed through raw.
            foreach ($slots as $slotName => $componentSlot) {
                $env->slot($slotName, $componentSlot);
            }

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
