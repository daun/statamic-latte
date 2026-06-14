<?php

namespace Daun\StatamicLatte\Latte\Support;

use Statamic\Facades\Cascade;
use Throwable;

/**
 * Runtime backing the {section} and {yield} Latte tags.
 *
 * Mirrors Statamic's own section/yield storage so content flows freely between
 * engines: contributions are written to the Statamic Cascade (the reliable
 * cross-engine store that Antlers {{ section }} and {{ yield }} use) and, as a
 * bonus, to Laravel's view factory for Blade @section/@yield interop.
 *
 * {yield} can't read its section inline — a layout's <head> yield renders before
 * a deep body partial defines the section. So {yield} emits a unique placeholder
 * token and the real content is substituted once, after the whole template has
 * rendered (see NormalizingEngine::get). This makes "section anywhere, yield
 * anywhere" work regardless of render order, exactly like Blade stacks.
 */
class Sections
{
    /** @var array<string, array{name: string, default: string}> */
    protected static array $pending = [];

    /**
     * Store a section's contents where every engine can read them.
     */
    public static function store(string $name, string $content): void
    {
        Cascade::instance()->sections()->put($name, $content);

        // Best-effort mirror into Laravel's view factory so Blade @yield can
        // see it too. Sections are flushed there after each render, which is
        // exactly why the Cascade write above is the reliable one.
        if ($factory = static::factory()) {
            $factory->startSection($name);
            echo $content;
            $factory->stopSection();
        }
    }

    /**
     * Emit a placeholder for a yielded section, resolved after rendering.
     */
    public static function placeholder(string $name, string $default = ''): string
    {
        $token = "\x00@latte-yield:".bin2hex(random_bytes(8))."\x00";
        static::$pending[$token] = ['name' => $name, 'default' => $default];

        return $token;
    }

    /**
     * Resolve a section's contents from any engine's store.
     */
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
     * Replace every yield placeholder found in the rendered output.
     *
     * Only tokens present in this chunk are resolved and forgotten, so nested
     * renders (e.g. {nocache}/{antlers}) don't consume the parent's pending
     * placeholders.
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

    /**
     * The Laravel view factory, shared as __env on every view.
     */
    protected static function factory()
    {
        try {
            return view()->shared('__env') ?: app('view');
        } catch (Throwable) {
            return null;
        }
    }
}
