<?php

namespace Daun\StatamicLatte\Latte\Support;

use Statamic\StaticCaching\Middleware\Cache as CacheMiddleware;
use Statamic\StaticCaching\NoCache\Session;

class NoCache
{
    /**
     * Stack of recording frames, one per cache fragment currently being captured.
     *
     * @var array<int, array<int, array{view: string, context: array, placeholder: string}>>
     */
    protected static array $captures = [];

    /**
     * Register a nocache region and return its placeholder; outside of
     * static caching, render the view directly instead.
     */
    public static function placeholder(string $view, array $context): string
    {
        if (! CacheMiddleware::isBeingUsedOnCurrentRoute()) {
            return view($view, $context)->render();
        }

        $placeholder = app(Session::class)->pushView($view, $context)->placeholder();

        foreach (static::$captures as $i => $capture) {
            static::$captures[$i][] = compact('view', 'context', 'placeholder');
        }

        return $placeholder;
    }

    /**
     * Re-push the regions recorded with a cached fragment and swap their
     * stale placeholders for the newly minted ones.
     */
    public static function replay(string $html, array $regions): string
    {
        foreach ($regions as $region) {
            $html = str_replace(
                $region['placeholder'],
                static::placeholder($region['view'], $region['context']),
                $html,
            );
        }

        return $html;
    }

    public static function startCapture(): void
    {
        static::$captures[] = [];
    }

    /** @return array<int, array{view: string, context: array, placeholder: string}> */
    public static function stopCapture(): array
    {
        return array_pop(static::$captures) ?? [];
    }
}
