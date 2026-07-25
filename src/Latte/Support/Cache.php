<?php

namespace Daun\StatamicLatte\Latte\Support;

use Carbon\Carbon;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache as IlluminateCache;
use Statamic\Facades\Site;
use Statamic\Facades\URL;

class Cache
{
    /**
     * Stack of open fragments, one per cache tag currently capturing output.
     *
     * @var array<int, ?array{0: Repository, 1: string, 2: ?Carbon}>
     */
    protected static array $fragments = [];

    /**
     * Open a cache fragment: on a hit, echo it and return false; otherwise
     * start capturing and return true. Balance with close() or abort().
     */
    public static function open(?array $params, string $contents): bool
    {
        if (! static::enabled($params)) {
            static::$fragments[] = null;

            return true;
        }

        $store = static::store($params);
        $key = static::key($params, $contents);

        if ($cached = $store->get($key)) {
            $html = is_array($cached) ? ($cached['html'] ?? '') : $cached;
            $regions = is_array($cached) ? ($cached['regions'] ?? []) : [];
            echo NoCache::replay($html, $regions);

            return false;
        }

        static::$fragments[] = [$store, $key, static::expires($params)];
        NoCache::startCapture();
        ob_start(fn () => '');

        return true;
    }

    /** Store the captured fragment with its nocache regions, then echo it. */
    public static function close(): void
    {
        if (! $fragment = array_pop(static::$fragments)) {
            return;
        }

        [$store, $key, $expires] = $fragment;
        $html = ob_get_clean();
        $regions = NoCache::stopCapture();

        $store->put($key, compact('html', 'regions'), $expires);

        echo $html;
    }

    /** Discard an open fragment after an error during capture. */
    public static function abort(): void
    {
        if (! array_pop(static::$fragments)) {
            return;
        }

        ob_end_clean();
        NoCache::stopCapture();
    }

    public static function enabled(?array $params = []): bool
    {
        $main = $params[0] ?? null;
        $if = $params['if'] ?? (is_bool($main) ? $main : true);

        return $if !== false
            && config('statamic.system.cache_tags_enabled', true)
            && request()->method() === 'GET';
    }

    public static function store(?array $params = []): Repository
    {
        $main = $params[0] ?? null;
        $tags = $params['tags'] ?? (is_array($main) ? $main : null);

        $store = IlluminateCache::store();
        if (is_array($tags) && count($tags)) {
            $store = $store->tags($tags);
        }

        return $store;
    }

    public static function expires(?array $params = []): ?Carbon
    {
        $main = $params[0] ?? null;
        $for = $params['for'] ?? (is_int($main) ? $main : null);
        $expires = $for ? now()->add("+{$for}") : null;

        return $expires;
    }

    public static function key(?array $params, ?string $contents): string
    {
        $main = $params[0] ?? null;
        $key = $params['key'] ?? (is_string($main) ? $main : null) ?? $contents;
        $auth = auth(config('statamic.users.guards.cp', 'web'));

        // Only the dimensions listed in scope vary the key (default: site + auth).
        $scope = $params['scope'] ?? ['site', 'auth'];
        $scope = is_array($scope) ? $scope : explode('|', (string) $scope);

        $parts = [
            'key' => $key,
            'params' => $params,
            'scope' => collect($scope)->mapWithKeys(fn ($s) => [$s => match ($s) {
                'site' => Site::current()->handle(),
                'auth' => $auth->check(),
                'user' => $auth->check() ? $auth->user()->id : 'guest',
                'page' => URL::makeAbsolute(URL::getCurrent()),
                default => null,
            }])->all(),
        ];

        return 'latte.statamic.cache.'.md5(serialize($parts));
    }
}
