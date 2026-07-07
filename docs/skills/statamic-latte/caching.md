# Caching: {cache} Fragments and {nocache} Holes

Two unrelated mechanisms plus the compiled-template cache. Don't conflate them.

## Contents
- {cache} — fragment caching
- Scope: what varies the cache key
- {cache} sharp edges
- {nocache} — static-cache hole punching
- The compiled-template cache

## {cache} — fragment caching

Caches a rendered region in Laravel's default cache store. `n:cache` on an element also works.

```latte
{cache for: '10 minutes'}
    {foreach $stocks as $stock}
        {$stock->fetchPrice()}
    {/foreach}
{/cache}
```

Parameters (all optional; a bare positional first argument is typed — bool → `if:`, array → `tags:`, int → `for:`, string → `key:`):

| Param | Meaning |
|---|---|
| `for:` | lifetime — Carbon interval string (`'2 hours'`) or int; omitted = cache forever |
| `if:` | condition; false renders uncached |
| `key:` | explicit cache key; default is a hash of the block's compiled content, so **editing the block busts the cache, changing the data does not** |
| `tags:` | cache tags (requires a tag-capable store: redis/memcached/array) |
| `scope:` | key dimensions, array or pipe string — see below |

Caching only happens on **GET requests** and while `statamic.system.cache_tags_enabled` is true; otherwise the body silently renders uncached (looks like "caching is broken" when testing with forms).

## Scope: what varies the cache key

Default scope is `['site', 'auth']`. Accepted as array or pipe string: `scope: 'site|user|page'`.

| Dimension | Varies by |
|---|---|
| `site` | current multisite handle |
| `auth` | **whether** someone is logged in (boolean — NOT per-user) |
| `user` | user id / `'guest'` — use for personalized fragments |
| `page` | current absolute URL — use for per-URL content (active nav state etc.) |

Unknown scope names silently no-op. **The default `auth` scope leaks personalized content between logged-in users** — anything user-specific inside `{cache}` needs `scope: 'site|user'` (or belongs in `{nocache}`).

## {cache} sharp edges

- **Falsy output never caches**: a block whose rendered output is `''` or `'0'` is a permanent cache miss — re-rendered and re-stored on every request, no error, no log. Don't wrap regions that can render empty.
- The **entire params array is serialized into the key** — any param change (even an unrecognized one, or a per-request value passed as a param) produces a different key.
- Don't put `{yield}` inside `{cache}`: yields are placeholder tokens resolved at the end of the render, so the cached fragment stores either request-specific content or an unresolvable token.
- Nesting `{nocache}` inside `{cache}` is unsupported (below) — there is no compile guard; it just silently freezes the placeholder.

## {nocache} — static-cache hole punching

Exempts a region from Statamic [static caching](https://statamic.dev/static-caching) — re-rendered on every request while the rest of the page is served cached:

```latte
{nocache}
    {if $logged_in}Welcome back, {$current_user->name}{else}Hello, Guest!{/if}
{/nocache}
```

Limitations:

- Works only with **application-level ("half measure") static caching**. Full file-based caching serves HTML straight from the web server — no PHP runs, nothing fills the hole (the JS mechanism that full-measure needs isn't implemented in this addon).
- **No nesting with `{cache}`**: a `{nocache}` inside `{cache}` gets its one-time placeholder markup frozen into the fragment — the "dynamic" region is served stale silently.
- The body is extracted and compiled as a separate view — it sees the page's cascade data, but treat it as a self-contained region.

## The compiled-template cache

Latte compiles `.latte` files to PHP in `storage/framework/views` (configurable via `latte.compiled`). Auto-refresh follows `app.debug` unless `latte.auto_refresh` is set — so in production **nothing checks template freshness: deploys must run `php artisan view:clear`**. Also clear after installing addons that register tags (the `{s:...}` block form binds at compile time) — see the gotcha in [statamic-tags.md](statamic-tags.md).
