---
name: caching
description: Covers the {cache} and {nocache} Latte tags, the fragment-cache runtime (Cache support class, key/scope/expiry), and Latte's compiled-template cache mechanics. Use when modifying src/Latte/Extensions/Nodes/CacheNode.php, NocacheNode.php, src/Latte/Support/Cache.php, or when debugging "fragment not caching", stale compiled templates, or nocache holes not being filled.
---

# Caching: {cache}/{nocache} tags and the two caches behind them

This skill covers fragment caching (`{cache}`), static-cache hole punching (`{nocache}`), and how Latte's compiled-template cache decides freshness. These are three different mechanisms that people routinely conflate; each has its own storage, its own invalidation rules, and its own failure modes.

## When to use this skill

- Adding or changing `{cache}` parameters or scope dimensions (edit `src/Latte/Support/Cache.php`).
- Changing compiled output of `{cache}`/`{nocache}` (edit `src/Latte/Extensions/Nodes/CacheNode.php` / `NocacheNode.php`).
- Debugging: a cached fragment re-renders every request, stale template output after code changes, `{nocache}` region frozen in statically cached pages.
- Writing tests that touch caching (`tests/Tags/CacheTest.php`, `tests/Tags/NoCacheTest.php`).

## The two caches (do not conflate them)

**1. Latte's compiled-template cache** — where compiled PHP lives.

- Directory: `config('latte.compiled') ?? config('view.compiled')` (set by miko/laravel-latte's `ServiceProvider::configure()`; in practice `storage/framework/views`).
- Files look like `welcome-latte--{10-char hash}.php`, each with a sibling `.lock` file.
- Freshness (latte/latte `Latte\Runtime\Cache::loadOrCreate`): when auto-refresh is on (default = `app.debug`), Latte computes `hash('xxh128', serialize([Engine::Version, loader getContent output, extension file mtimes]))` and compares it to the content of the `.lock` file. Mismatch → recompile. There is NO mtime check on the template file itself.
- Consequence: `LaravelViewLoader::isExpired()` in `src/Latte/LaravelViewLoader.php` is **dead code**. Latte 3.1's `Latte\Loader` interface declares only `getContent`/`getReferredName`/`getUniqueId`; nothing calls `isExpired`, and `TagMethodLoader` doesn't proxy it. Do not "fix" cache staleness by editing it — it never runs.
- Because the signature hashes the loader's `getContent()` output (which is POST tag-syntax rewrite) plus extension file mtimes, rewrite changes in `TagMethodSyntax`/`TagExpressionSyntax` DO auto-invalidate any template whose post-rewrite source changes — the loader IS `TagMethodLoader`, so its rewritten output is in the signature. Node `print()` changes do NOT invalidate: node classes aren't extensions and their output isn't hashed. When iterating on node code (or rewrite edits that leave the rewritten source byte-identical): delete the compiled dir or run `php artisan view:clear`.

**2. The `{cache}` fragment cache** — rendered HTML fragments in Laravel's cache store.

- Runtime lives in `src/Latte/Support/Cache.php` (static methods `enabled`/`store`/`expires`/`key`), compiled calls emitted by `CacheNode::print()`.
- Storage: the app's default Illuminate cache store (`Cache::store()`), optionally wrapped in `->tags(...)`.
- Keys are prefixed `latte.statamic.cache.` — completely unrelated to compiled-template files.

`{nocache}` is neither of these: it delegates to Statamic's static-caching machinery (see below).

## {cache}: how it works

`CacheExtension::getTags()` registers `cache` → `CacheNode::create` and `nocache` → `NocacheNode::create`. `n:cache` on an element also works (Latte auto-derives n: forms).

`CacheNode::create` parses arguments into an `ArrayNode`; the body stays a normally-compiled Latte subtree (NOT extracted to a temp view — unlike `{nocache}`). `CacheNode::print` emits, in the compiled template:

1. `$ʟ_params = <args>` then a gate: `Cache::enabled($ʟ_params)`.
2. If enabled: compute store, key (with `%dump` of `md5($this->content->print($context))` — the **compile-time** hash of the compiled body — as fallback key), expiry; on hit echo the cached string; on miss run the body inside `ob_start(fn() => '')` / `ob_get_clean()`, `put()` it, echo it.
3. Else branch: the body runs uncached.

**The body is printed TWICE into the compiled file** (miss branch + disabled branch). Side effects in the body's compiled representation exist in both branches, and compiled file size doubles for large regions. Keep this in mind before putting block definitions or huge markup inside `{cache}`.

Because the fallback key is the md5 of the compiled body, editing the block's contents automatically busts the cache. This is load-bearing: without it, two `{cache}` blocks with identical params would collide and edited templates would serve stale fragments forever (there is no default `for:`). Never remove the `$contents` fallback from `Cache::key()`.

### Runtime parameter semantics (`src/Latte/Support/Cache.php`)

Every method accepts the raw params array and supports a bare positional first arg (`$params[0]`) OR named params. Positional type decides meaning: bool → `if:`, array → `tags:`, int → `for:`, string → `key:`.

- `Cache::enabled($params)` — true only when `if:` (or bare bool) `!== false` AND `config('statamic.system.cache_tags_enabled', true)` AND `request()->method() === 'GET'`. POST/PUT requests silently render uncached — this looks like "caching is broken" when testing with forms.
- `Cache::store($params)` — default Illuminate store; wrapped in `->tags($tags)` when `tags:` (or bare array) is a non-empty array. Requires a tag-capable store (redis/memcached/array).
- `Cache::expires($params)` — `for:` (or bare int) → `now()->add("+{$for}")`, so `for: "2 hours"` is a Carbon interval string. Null = cache forever.
- `Cache::key($params, $contents)` — key = `key:` param, else bare string positional, else `$contents` (compile-time body md5). Final key:

  ```php
  'latte.statamic.cache.'.md5(serialize([
      'key' => $key,
      'params' => $params,
      'scope' => /* resolved scope map */,
  ]))
  ```

  Note `params` is serialized wholesale into the key — any param change (even an unknown one) produces a different key.

### Scope: what varies the key

Default scope is `['site', 'auth']`. Accepted as array or pipe string (`scope: 'site|user|page'`). Dimensions (see the `match` in `Cache::key`):

- `site` → `Site::current()->handle()`
- `auth` → **boolean** logged-in check via the guard from `config('statamic.users.guards.cp', 'web')`. The default scope varies by WHETHER someone is logged in, not by WHO. Personalized content inside a default-scoped `{cache}` leaks between logged-in users.
- `user` → user id or `'guest'` (use for per-user fragments)
- `page` → `URL::makeAbsolute(URL::getCurrent())` (use for per-URL fragments, e.g. anything rendering the current URL or active-nav state)
- anything else → `null` (silently no-ops; there is no validation of scope names)

To add a scope dimension or param: edit `src/Latte/Support/Cache.php` only. `CacheNode::print` passes the whole params array to every method, so no node change is needed for new named params. Add cases to `tests/Tags/CacheTest.php`.

## KNOWN SHARP EDGE: falsy cache hits are permanent misses

`CacheNode::print` emits the hit check as:

```php
if ($ʟ_output = $ʟ_store->get($ʟ_key)) {
```

That is a **truthiness** check, not `!== null`. A `{cache}` block whose rendered output is `''` (e.g. a conditional that renders nothing) or the string `'0'` is a silent permanent cache miss: the fragment is re-rendered AND re-stored on every single request. No error, no log — it just never caches.

If someone reports "this cache block never hits" and the output can be empty/`'0'`, this is the cause. The fix shape, if it's ever needed, is in `CacheNode::print`:

```php
$ʟ_output = $ʟ_store->get($ʟ_key);
if ($ʟ_output !== null) {
```

(Any such change must keep the miss branch storing `ob_get_clean()`'s string output, and needs a regression test in `tests/Tags/CacheTest.php` caching an empty-rendering block twice and asserting the second render doesn't re-execute the body.)

## {nocache}: static-cache hole punching

`NocacheNode` (uses `Concerns\ExtractsToTemporaryView`):

- `create()` parses optional args, then captures the body as **raw text** (lexer syntax off, ContentType Text via `disableParserForTag`/`restoreParserForTag`) — the inner Latte is NOT compiled into the parent template.
- `print()` writes the body to a temp view via `saveContentToView()` — a file `latte-tag-content-{sha1(content)}.latte` in `config('view.compiled')`, addressed as `statamic-latte-temp::latte-tag-content-{sha1}` (namespace registered in `ServiceProvider::registerViewNamespace()`) — and emits:

  ```php
  echo app("Statamic\StaticCaching\NoCache\BladeDirective")->handle(<tempView>, ["__layout_parent" => $this->getName()] + <args>);
  ```

  The FQCN is a baked string in compiled output (rename hazard: grep compiled-code strings after any refactor). `__layout_parent` is injected so `LayoutExtension::resolveLayout` does NOT wrap the temp view in the page layout again — remove it and you get recursive/duplicated layout chrome.

At runtime, Statamic's `BladeDirective` emits only a placeholder — `<span class="nocache" data-nocache="KEY">NOCACHE_PLACEHOLDER</span>` — storing the region (view + context) in the NoCache session; the region content is never inside the span. Statamic's `NoCacheReplacer` then substitutes the whole span with the freshly rendered region: on the initial response as it is prepared for caching, and on every served statically-cached response. (If the static-cache middleware isn't in use on the route, `BladeDirective` renders the view inline with no span at all; if it's armed but no replacer runs — as in `NoCacheTest`'s direct render — the raw placeholder span survives into the output, which is exactly what the test asserts.) Because the temp view keeps the `.latte` extension, the re-render re-enters `NormalizingEngine`.

**Constraint: nocache only works with application-level static caching** (Statamic's "half measure", where cached responses are still served by the booted Laravel app). It does NOT work with full file-based static caching, because file-based caching serves HTML straight from disk via the web server — no PHP boots, so nothing exists to fill the hole. Full-measure support would require a JavaScript replacement mechanism this addon has not implemented (README "Limitations" section, ~line 344).

**Constraint: nesting `{cache}` and `{nocache}` is unsupported** (README "Limitations"). There is no compile-time guard — it just misbehaves silently: the `{cache}` fragment freezes the nocache region's one-time `<span class="nocache">` placeholder markup from the first render, so the "dynamic" region is served stale from the fragment cache on every subsequent request. Same trap applies to `{yield}` inside `{cache}`: yields are NUL-byte placeholder tokens until `Sections::resolve` runs at the end of `NormalizingEngine::get`, so a `{cache}` block wrapping a `{yield}` stores either request-specific resolved content or an unresolvable token. Keep `{nocache}` and `{yield}` outside `{cache}`.

## Test setup requirements

`tests/Tags/NoCacheTest.php` has a mandatory `beforeEach`:

```php
config(['app.key' => 'base64:...']);
$this->get('/');
```

Why: Statamic's NoCache machinery is inert unless a real request has passed through the static-cache middleware — the warm-up `$this->get('/')` arms it, and the session/encryption used along the way needs `app.key` set (Testbench doesn't set one). Without both, `BladeDirective` renders nothing recognizable and the `<span class="nocache"` assertion fails. Any new nocache test file needs the same setup.

`tests/Tags/CacheTest.php` needs no such setup but relies on these patterns — copy them:
- `request()->setMethod('POST')` to prove the GET-only gate.
- `Carbon::setTestNow(...)` for `for:` durations and for changing the rendered output between renders.
- `Cache::tags('a')->flush()` for the `tags:` param (the array store in tests supports tags).
- Cache-hit assertion pattern: render the same block twice with different data, assert the FIRST render's output appears the second time (`->assertSee('B A')`).

## Debug recipes

**"Fragment not caching" checklist (in order):**
1. Falsy output? If the block can render `''`/`'0'`, it's the truthiness bug above — permanent miss by design flaw.
2. Is the request a GET? `Cache::enabled` hard-rejects everything else.
3. `config('statamic.system.cache_tags_enabled')` true?
4. `if:` param (or bare bool positional) evaluating false?
5. Scope key changing per request? `scope: 'page'` on URLs with volatile query strings, or params containing per-request values — remember the ENTIRE params array is serialized into the key.
6. Store misconfigured? `tags:` on a store without tag support throws; array/file drivers behave differently across requests. Dump the key: temporarily `logger()` inside `Cache::key()`, or grep the compiled template for `StatamicLatte\Latte\Support\Cache` (or `ʟ_cache::key`) to confirm the emitted code — the `latte.statamic.cache.` prefix is added at runtime inside `Cache::key()` and never appears in compiled files.

**"Stale compiled template":**
- Latte freshness = xxh128 of (Engine version + post-rewrite source + extension class file mtimes) vs the `.lock` file. The signature reflects the Extension classes themselves (e.g. `CacheExtension.php`), NOT node classes — editing `CacheNode::print()` does NOT invalidate already-compiled templates. Rewrite changes in `TagMethodSyntax`/`TagExpressionSyntax` DO invalidate templates whose post-rewrite source changes (the signature hashes the rewritten source). When iterating on node code, always clear.
- Fix: delete everything in `config('view.compiled')` or run `php artisan view:clear`. This also removes `latte-tag-content-*` temp views; they regenerate on next compile.
- Auto-refresh off in production (`app.debug` false, no `latte.auto_refresh` override) means NO freshness check at all — deploys must clear compiled views.

**"Nocache hole not filled" (dynamic region stays stale):**
1. Which static caching strategy? `config('statamic.static_caching.strategy')` — file-based ("full measure") cannot work, period. Only the application driver ("half measure") fills holes.
2. Is the `{nocache}` inside a `{cache}` block? Unsupported — the placeholder span got fragment-cached.
3. In tests: missing the `app.key` + warm-up `$this->get('/')` beforeEach.
4. Check the raw response for `<span class="nocache" data-nocache=`. Present = the hole was punched but not replaced (middleware/strategy problem). Absent = the node never ran (compile problem; check the compiled template for `BladeDirective`).

## Invariants

- Never remove the compile-time body-hash fallback from `Cache::key()` — key collisions and permanent staleness follow.
- Never change `getUniqueId` semantics in `LaravelViewLoader` casually — it feeds Latte's compiled-file hash; changing it orphans every compiled template.
- `NocacheNode` must keep injecting `__layout_parent` and must keep the `.latte` temp-view extension (the re-render must go through the Latte engine).
- The `statamic-latte-temp` namespace must keep pointing at `config('view.compiled')` (`ServiceProvider::registerViewNamespace`) — `saveContentToView` writes there and the nocache temp view is re-resolved on later, statically-cached requests.
- FQCN strings baked into compiled output (`\Daun\StatamicLatte\Latte\Support\Cache::class` in `CacheNode::print`, `"Statamic\StaticCaching\NoCache\BladeDirective"` in `NocacheNode::print`) must be updated on any rename — grep `src/` for string-literal FQCNs in `print()` methods.
- Don't add compile-time guards or params to `CacheNode` when a runtime param suffices — the whole params array already reaches every `Cache::*` method.

## How to verify a change

```sh
./vendor/bin/pest tests/Tags/CacheTest.php tests/Tags/NoCacheTest.php
composer test        # full suite (~19s)
composer analyse     # PHPStan
composer lint        # Pint check
```

To inspect what actually compiled: render once via `$this->latte('...')` in a test, then read the newest file in the compiled dir and search for `StatamicLatte\Latte\Support\Cache` / `ʟ_cache::key` (cache) or `BladeDirective` (nocache) — the `latte.statamic.cache.` key prefix is runtime-only and never appears in compiled files. When running via the test suite, the compiled dir is the Testbench skeleton's: `vendor/orchestra/testbench-core/laravel/storage/framework/views/` (files persist after a test run, named like `-latte--{10-char hash}.php`).

## Related skills

- **extensions-and-nodes** — node authoring, `ExtractsToTemporaryView`, adding tags/extensions.
- **template-syntax** — user-facing tag surface, README contract.
- **orientation** — boot sequence, `ServiceProvider`, engine wiring.
- **testing** — `$this->latte()` helper, test conventions.
- **debugging** — general compiled-output and engine-wiring inspection.
- **data-layer**, **tag-bridge**, **quality-gates** — siblings for data wrapping, `{s:...}` tags, and CI.
