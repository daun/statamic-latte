<?php

namespace Daun\StatamicLatte\Support;

use Carbon\Carbon;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Statamic\Facades\Site;
use Statamic\Facades\URL;

class CacheNode
{
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

        $store = Cache::store();
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
        $scope = $params['scope'] ?? ['site', 'auth'];
        $site = Site::current()->handle();

        $parts = [
            'key' => $key,
            'params' => $params,
            'auth' => $auth->check(),
            'site' => $site,
            'scope' => collect($scope)->flip()->map(fn ($_, $s) => match ($s) {
                'page' => URL::makeAbsolute(URL::getCurrent()),
                'user' => $auth->user()?->id ?? 'guest',
                default => null,
            })->all(),
        ];

        $hash = md5(json_encode($parts));

        return "latte.statamic.cache.{$hash}";
    }
}
