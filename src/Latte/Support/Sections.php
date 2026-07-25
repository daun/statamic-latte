<?php

namespace Daun\StatamicLatte\Latte\Support;

use Statamic\Facades\Cascade;
use Throwable;

/**
 * Runtime backing the {section} and {yield} tags. Sections are written to the
 * Statamic Cascade (cross-engine with Antlers) and mirrored into Laravel's view
 * factory for Blade interop. {yield} can't read inline — it may render before
 * its section is defined — so it emits a placeholder token substituted after
 * the whole template has rendered (see NormalizingEngine::get).
 */
class Sections
{
    /** @var array<string, array{name: string, default: string}> */
    protected static array $pending = [];

    /** Render-tree depth; nested {nocache}/{antlers} re-enter the engine. */
    protected static int $depth = 0;

    public static function beginRender(): void
    {
        static::$depth++;
    }

    /** On the outermost render, drop any placeholders left unresolved. */
    public static function endRender(): void
    {
        if (--static::$depth <= 0) {
            static::$depth = 0;
            static::$pending = [];
        }
    }

    public static function store(string $name, string $content): void
    {
        Cascade::instance()->sections()->put($name, $content);

        // Best-effort mirror for Blade @yield; the factory flushes sections
        // after each render, which is why the Cascade write is the reliable one.
        if ($factory = static::factory()) {
            $factory->startSection($name);
            echo $content;
            $factory->stopSection();
        }
    }

    /** Emit a placeholder for a yielded section, resolved after rendering. */
    public static function placeholder(string $name, string $default = ''): string
    {
        $token = "\x00@latte-yield:".bin2hex(random_bytes(8))."\x00";
        static::$pending[$token] = ['name' => $name, 'default' => $default];

        return $token;
    }

    /** Resolve a section's contents from any engine's store. */
    public static function content(string $name): ?string
    {
        if ($factory = static::factory()) {
            $value = (string) $factory->yieldContent($name);
            if ($value !== '') {
                return $value;
            }
        }

        $value = Cascade::instance()->sections()->get($name);

        return $value !== null ? (string) $value : null;
    }

    /**
     * Replace yield placeholders in the rendered output. Only tokens present in
     * this chunk are resolved, so nested renders don't consume the parent's.
     */
    public static function resolve(string $output): string
    {
        if (! static::$pending) {
            return $output;
        }

        $replacements = [];
        foreach (static::$pending as $token => $meta) {
            if (str_contains($output, $token)) {
                $replacements[$token] = static::content($meta['name']) ?? $meta['default'];
                unset(static::$pending[$token]);
            }
        }

        return $replacements ? strtr($output, $replacements) : $output;
    }

    /** The Laravel view factory, shared as __env on every view. */
    protected static function factory()
    {
        try {
            return view()->shared('__env') ?: app('view');
        } catch (Throwable) {
            return null;
        }
    }
}
