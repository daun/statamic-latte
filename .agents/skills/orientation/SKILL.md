---
name: orientation
description: Entry-point map of the statamic-latte codebase — what the addon is, where every src/ and tests/ directory lives, how a .latte view renders end-to-end, and which sibling skill to load for a given task. Use when new to this repo, unsure where code lives, or deciding which skill covers a change.
---

# Orientation: statamic-latte codebase map

This repo is `daun/statamic-latte`, a Composer package of type `statamic-addon` that plugs the **Latte** templating engine into **Statamic 6** sites so `.latte` files are first-class views alongside Antlers. Stack: PHP ^8.3, Laravel 12/13 (constrained transitively via `statamic/cms` and `miko/laravel-latte`, not pinned directly), Statamic ^6.0, Latte via `miko/laravel-latte` ^3.0. CI matrix: PHP 8.3–8.5 x Laravel 12/13 x Statamic 6.

## When to use this skill

- First contact with this codebase.
- "Where does X live?" / "Which class does Y?"
- Deciding which of the other skills (data-layer, tag-bridge, extensions-and-nodes, caching, template-syntax, testing, debugging, quality-gates) to load for a task.
- Understanding why the boot sequence or directory layout looks the way it does.

## High-level architecture

Five pieces, all wired from one class (`src/ServiceProvider.php`):

1. **ServiceProvider wiring** — `Daun\StatamicLatte\ServiceProvider` extends `Statamic\Providers\AddonServiceProvider`. All setup happens in `bootAddon()`, which Statamic defers until the whole app has booted (so the `Latte\Engine` singleton from miko/laravel-latte already exists). It runs, in order: `installLoader()`, `installExtensions()`, `installEngine()`, `registerViewNamespace()`.
2. **NormalizingEngine render boundary** — `src/Latte/NormalizingEngine.php` extends `Miko\LaravelLatte\LatteEngine`, overriding only `get()`. Every piece of data entering a `.latte` render passes `Content::wrapAll()`; every rendered string passes `Sections::resolve()` on the way out. This is THE choke point between Statamic's data model and Latte.
3. **Data layer** (`src/Data/`) — `Content` wraps Statamic Values/Augmentables so they behave sanely in Latte (`{if}` truthiness, `->prop` access, iteration); `Deferred` postpones expensive relationship augmentation; `Resolver` unwraps values to their final scalar/object form; `Normalizer` is a deprecated delegating shim (kept until 3.0 because already-compiled templates on user sites reference it — do not delete).
4. **Tag bridge** (`src/Latte/Extensions/TagExtension.php`, `src/Latte/Extensions/Nodes/TagNode.php`, `src/Latte/Support/Tags.php`, `TagArguments.php`, `TagMethodSyntax.php`, `TagExpressionSyntax.php`, `src/Latte/Loaders/TagMethodLoader.php`) — lets templates call any Statamic tag as `{s:collection ...}`, `{s:link /}`, or the inline expression form `(s:...)`. Statamic resolves tag methods at runtime, Latte registers tags at compile time — the bridge reconciles that via source rewriting before compilation.
5. **Extension set + loaders** — nine addon Latte extensions (list below) add Statamic behaviors ({antlers}, {cache}/{nocache}, layout auto-resolution, modifiers as filters, {section}/{yield}, {slot}, n:attr normalization, resolve helpers, s: tags). `LaravelViewLoader` makes Latte resolve templates through Laravel's view finder (dot notation, namespaces, relative paths); `TagMethodLoader` decorates it to rewrite Statamic tag syntax in the source.

## Where things live

### src/

| Path | Role |
|---|---|
| `src/ServiceProvider.php` | Single bootstrap point; `$defaultExtensions` list, loader/extension/engine installation, `statamic-latte-temp` view namespace |
| `src/helpers.php` | Composer-autoloaded (`files`); defines global `resolve_value(...)` delegating to `Resolver::actual` |
| `src/Data/Content.php` | Lazy wrapper for Statamic Values/Augmentables; `wrap()`/`wrapAll()`/`unwrap()` statics |
| `src/Data/Deferred.php` | Defers relationship-field augmentation until actually touched |
| `src/Data/Resolver.php` | Unwraps values to their actual final value (delegates to Statamic's Blade `value()` helper) |
| `src/Data/Normalizer.php` | DEPRECATED shim delegating to Content; keep until 3.0 (compiled templates on disk reference it) |
| `src/Latte/NormalizingEngine.php` | Laravel view engine for `.latte`; wraps data in, resolves section placeholders out |
| `src/Latte/LaravelViewLoader.php` | Latte `Loader` backed by Laravel's view finder (IO + name resolution) |
| `src/Latte/Loaders/TagMethodLoader.php` | Loader decorator: rewrites Statamic tag syntax in source before compilation |
| `src/Latte/Extensions/AntlersExtension.php` | `{antlers}...{/antlers}` islands rendered by the Antlers engine |
| `src/Latte/Extensions/AttributeNormalizationExtension.php` | Compiler pass unwrapping Content in n:attributes |
| `src/Latte/Extensions/CacheExtension.php` | `{cache}` / `{nocache}` tags |
| `src/Latte/Extensions/LayoutExtension.php` | Auto-resolves layout from Statamic entry data (`current_layout`) |
| `src/Latte/Extensions/ModifierExtension.php` | Exposes all Statamic modifiers as Latte filters (context arg is deliberately `[]`) |
| `src/Latte/Extensions/ResolverExtension.php` | `resolve()` / `r()` template functions + filters |
| `src/Latte/Extensions/SectionExtension.php` | `{section}` / `{yield}` cross-engine content bus |
| `src/Latte/Extensions/SlotExtension.php` | `{slot}` as an exact alias of `{block}` |
| `src/Latte/Extensions/TagExtension.php` | Registers the `s:` tag bridge + `statamic()`/`s()` functions |
| `src/Latte/Extensions/Nodes/TagNode.php` | Compile-time node for `{s:*}` tags; holds the `$unsupportedTags` blocklist |
| `src/Latte/Extensions/Nodes/AntlersNode.php` | Extracts `{antlers}` bodies to temp `.antlers.html` views |
| `src/Latte/Extensions/Nodes/CacheNode.php` / `NocacheNode.php` | Compile `{cache}` / `{nocache}` bodies |
| `src/Latte/Extensions/Nodes/SectionNode.php` / `YieldNode.php` | Compile `{section}` / `{yield}` |
| `src/Latte/Extensions/Nodes/Concerns/ExtractsToTemporaryView.php` | Trait: captures raw tag bodies and persists them as content-addressed temp views |
| `src/Latte/Support/Tags.php` | Runtime tag fetching/normalization (paginator recovery, result wrapping) |
| `src/Latte/Support/TagArguments.php` | Parses/restores Statamic-style nested param keys |
| `src/Latte/Support/TagMethodSyntax.php` / `TagExpressionSyntax.php` | Source-level rewrites of `{s:tag:method}` and `(s:...)` forms |
| `src/Latte/Support/Sections.php` | Runtime store for sections; `\x00`-delimited yield placeholders resolved post-render |
| `src/Latte/Support/Cache.php` | Runtime backing for `{cache}` (key scope, store access) |

### tests/

| Path | Role |
|---|---|
| `tests/TestCase.php` | Orchestra Testbench base: registers addon + Miko + Statamic providers, fixtures-backed views/content, Manifest registration (without it `bootAddon()` silently no-ops) |
| `tests/Pest.php` | Binds `TestCase` to `Feature/` and `Tags/` only; defines `fixtures_path()` / `statamic_package_path()` |
| `tests/Feature/` | 13 files: engine behavior (ServiceProviderTest, EngineDelegationTest, LoaderTest, LayoutTest, ModifierTest, TagTest, SlotTest, NAttributeTest, SmartAttributesTest, HelperTest, ResolverTest, ContentWrapTest, DeferredTest) |
| `tests/Tags/` | **~38 files — the LARGEST suite.** Per-Statamic-tag compatibility tests (CollectionTest, NavTest, FormTest, NoCacheTest, UnsupportedTagsTest, …) |
| `tests/Unit/` | 2 app-less parser tests (TagExpressionSyntaxTest, TagMethodSyntaxTest). NOT bound to TestCase — no Laravel container available |
| `tests/Concerns/` | InteractsWithLatteViews (`$this->latte('...')` inline-template helper), MocksFrontendRequests, ResolvesStatamicConfig |
| `tests/fixtures/` | views, content, blueprints, forms, users, assets-files, svg, vite |

Note: AGENTS.md claims tests are "split across `tests/Feature/` and `tests/Unit/`" — **stale**; it omits `tests/Tags/`, which is the largest suite.

## How a .latte view renders, end to end

1. Something calls `view('some.view')` (a controller, Statamic's `Statamic\View\View`, a test).
2. Laravel's `FileViewFinder` matches `some/view.latte` — the `.latte` extension was (re-)prepended by the addon, so it wins over a same-named `.antlers.html`.
3. The view `Factory` resolves engine `latte` from the `EngineResolver` → gets `Daun\StatamicLatte\Latte\NormalizingEngine` (the addon's override, not Miko's `LatteEngine`).
4. `NormalizingEngine::get($path, $data)` calls `Sections::beginRender()` (depth counter for nested re-entrant renders), then wraps all data via `Content::wrapAll()` — Statamic Entries/Values become lazy `Content` wrappers.
5. `parent::get()` (Miko's `LatteEngine`) sets `DeterministicKeys::setPath($path)` (Livewire keys — never skip this) and calls `Latte\Engine::renderToString()`.
6. If the template isn't compiled yet (or is stale), Latte asks its loader for source: `TagMethodLoader::getContent()` → `LaravelViewLoader::getContent()` reads the file → `rewrite()` lowers Statamic tag syntax (`TagExpressionSyntax` then `TagMethodSyntax`), skipping Latte comments and `{antlers}` islands (the `PROTECTED` regex).
7. Latte compiles the rewritten source to a PHP class in `config('latte.compiled') ?? config('view.compiled')` (Miko's provider); the addon's extensions contribute nodes ({s:*}, {antlers}, {cache}, {section}, …) and compiler passes.
8. `{include}`/`{layout}` names resolve through `LaravelViewLoader::getReferredName()` — dot notation and namespaces via the view finder, `./`/`../` relative to the referring file. `LayoutExtension`'s `coreParentFinder` provider auto-applies the entry's `current_layout` as the Latte layout.
9. The compiled template executes; `{antlers}`/`{nocache}` bodies were extracted at compile time to content-addressed temp views under the `statamic-latte-temp::` namespace (mapped unconditionally to `config('view.compiled')` by `registerViewNamespace()` — a site setting `latte.compiled` would break this alignment) and re-enter the view pipeline at runtime.
10. Back in `NormalizingEngine::get()`, `Sections::resolve()` substitutes any `{yield}` placeholder tokens with stored section content, and `Sections::endRender()` closes the frame (in a `finally`). The string is returned to Laravel/Statamic.

## Extension registration and the booted() wrapper

`ServiceProvider::$defaultExtensions` — verified exact list and order (alphabetical):

1. `AntlersExtension`
2. `AttributeNormalizationExtension`
3. `CacheExtension`
4. `LayoutExtension`
5. `ModifierExtension`
6. `ResolverExtension`
7. `SectionExtension`
8. `SlotExtension`
9. `TagExtension`

They are added to the shared `Latte\Engine` container singleton AFTER Latte core and Miko's extensions, so same-named providers/filters from the addon win (e.g. `LayoutExtension`'s `coreParentFinder` deliberately overwrites Miko's config-based one). Every extension is constructed as `new $extension($engine)` even though only `ModifierExtension` declares an `Engine` constructor param — PHP silently drops surplus args, but if you add a constructor to another extension its first param will receive the engine.

`installEngine()` is wrapped in `$this->app->booted(...)`: miko/laravel-latte's own provider registers the `latte` view engine resolving to Miko's `LatteEngine`; the addon must re-register AFTER that so `NormalizingEngine` wins. Since `bootAddon()` itself already runs post-boot (Statamic defers it), the closure fires immediately — it is kept as insurance so the override always lands last regardless of provider order. The `addExtension` call also re-prepends `.latte` in the finder's extension list.

Never `new Latte\Engine` — Miko's provider, the loader, the extensions, and `NormalizingEngine` must all share the one container singleton (`app(Latte\Engine::class)`).

## Documentation trust order

**code > tests > docs/ design documents > README > AGENTS.md**

- `docs/plan-data-layer-rebase.md` + `docs/report-data-layer-rebase.md` — current data-layer design, acceptance criteria, non-goals. Read these before git archaeology.
- `docs/antlers-blade-bridge-research.md` — research toward a component bridge (pairs with the unmerged `feat/components` branch).
- `README.md` — the de-facto user-facing behavior spec; every documented template feature is backed by tests and must keep working.
- `AGENTS.md` — useful but has **known-stale claims**:
  - Says "PHPStan 3 (level 5)". There is no PHPStan 3 — installed is **larastan 3.10.0 on phpstan/phpstan 2.2.2** (verify with `composer show phpstan/phpstan larastan/larastan`). AGENTS.md conflates the larastan major with PHPStan.
  - Omits `tests/Tags/` entirely (see above).
  - Its hard rule IS valid: prefix all local variables emitted into compiled/processed PHP with `$ʟ_` (U+029F) so generated locals can never collide with template-author variables.

## Historical context that explains odd layout

- The addon originally used a different Laravel-Latte integration and later switched to miko/laravel-latte (commit `026d9b8` "Switch laravel latte integration") and renamed the loader (commit `6f1c37a` "Rename loader"). That is why `src/Latte/LaravelViewLoader.php` sits at the top level while `src/Latte/Loaders/TagMethodLoader.php` sits in a `Loaders/` subdirectory — asymmetry by history, not by design.
- `TagMethodLoader`'s own docblock overstates its scope: it claims "expiry" is among the delegated responsibilities, but the class has NO `isExpired` proxy — it implements/delegates only `getContent`/`getReferredName`/`getUniqueId` (the entire Latte 3.1 `Loader` interface). `LaravelViewLoader::isExpired()` is dead code from the pre-Latte-3.1 interface; freshness is now Latte's content-hash refresh signature.
- `src/Data/Normalizer.php` is a deprecated shim — compiled templates on user sites reference its FQCN, so it stays until 3.0.

## Branch state

- `main` is the primary branch and PR target.
- `develop` exists locally and remotely but is currently **0 commits ahead** of main (verify: `git log --oneline main..origin/develop`) — don't assume dead without checking, but don't target it either.
- `feat/components` is unmerged and ~6 commits ahead (verify: `git log --oneline main..feat/components`; component support, pairs with `docs/antlers-blade-bridge-research.md`). Check it before starting component-related work.

## Verifying you're oriented correctly

```bash
composer test        # ./vendor/bin/pest — full suite, ~380 tests, ~20s (count drifts as tests are added)
composer lint        # pint --test (check only)
composer analyse     # phpstan level 5 via larastan, src/ only, needs --memory-limit=2G (baked in)
./vendor/bin/pest tests/Feature/ServiceProviderTest.php   # wiring smoke test
```

Runtime wiring checks (in a test — this standalone package has no artisan/workbench, and a bare testbench shell skips Manifest registration so `bootAddon()` no-ops, giving false negatives; tinker only works inside a host Statamic site). All three are already asserted by `tests/Feature/ServiceProviderTest.php` and `tests/Feature/EngineDelegationTest.php`:
- `View::getEngineResolver()->resolve('latte')` must be `NormalizingEngine`.
- `app(Latte\Engine::class)->getExtensions()` must contain all nine `$defaultExtensions` classes.
- `View::getFinder()->getHints()` must contain `statamic-latte-temp`.

Fastest repro harness for any template behavior: `$this->latte('{$x}', ['x' => 'ok'])->assertSee('ok')` in a Feature test (pass `squish: false` for whitespace-sensitive output).

## Which skill do I need?

| Task looks like… | Skill |
|---|---|
| Content/Deferred/Resolver behavior, truthiness in `{if}`, wrapping/unwrapping at the render boundary, `NormalizingEngine::get` | **data-layer** |
| `{s:collection}`, `(s:...)` expressions, tag params/pagination, `Tags.php`/`TagArguments`/`TagNode`, `$unsupportedTags` | **tag-bridge** |
| Adding/changing a Latte extension or Node ({antlers}, {section}/{yield}, {slot}, layout resolution, modifiers-as-filters, n:attributes, temp-view extraction) | **extensions-and-nodes** |
| `{cache}`/`{nocache}`, static-cache hole punching, stale compiled templates, compile-cache invalidation | **caching** |
| Source rewriting: `TagMethodSyntax`/`TagExpressionSyntax`, the PROTECTED regex (defined in `src/Latte/Loaders/TagMethodLoader.php`, not in Support/), what template authors may write | **template-syntax** |
| Writing/fixing tests, fixtures, `$this->latte()`, TestCase/Testbench setup, Manifest registration | **testing** |
| "It renders wrong and I don't know why": inspecting compiled output, post-rewrite source, wiring checks, yield tokens in output | **debugging** |
| composer scripts, CI matrix, Pint/PHPStan config, changelog/release conventions, commit style, `$ʟ_` rule | **quality-gates** |

## Related skills

data-layer, tag-bridge, extensions-and-nodes, caching, template-syntax, testing, debugging, quality-gates
