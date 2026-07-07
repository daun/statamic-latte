---
name: testing
description: Writing and running tests for statamic-latte — Pest/Testbench infrastructure, fixture content, the CLASSIFY tag-compat taxonomy, and recipes for tag-compat, feature, and unit tests. Use when adding or changing anything under tests/, when a test fails mysteriously (state leakage, empty tag output, escaping), or before touching tests/fixtures/.
---

# Testing

This suite boots a full Statamic 6 + Latte app inside Orchestra Testbench so tests render real `.latte` templates against real flat-file content in `tests/fixtures/`. Three suites by directory convention: **Feature** (engine behavior: layouts, loader, slots, modifiers, resolver), **Tags** (per-Statamic-tag compatibility — the largest suite, 38 files), **Unit** (pure string-rewrite classes, no app). `phpunit.xml` defines a single testsuite over `./tests`; the split is directories only.

## When to use this skill

- Adding a compat test for a Statamic tag, a feature test for engine behavior, or a unit test for syntax rewriters.
- Adding or changing fixture content under `tests/fixtures/`.
- Debugging test failures: phantom state between tests, tags rendering empty, assertSee mismatches.

## Stack

- Pest 4 + Testbench 11 locally (`composer.json` require-dev: `pestphp/pest ^4.0`, `orchestra/testbench ^11.0`). CI matrix (`.github/workflows/ci.yml`): PHP 8.3–8.5 × Laravel 12/13 × Statamic 6; Laravel 12 runs Testbench 10 + Pest 3, Laravel 13 runs Testbench 11 + Pest 4. Don't use Pest-4-only APIs without checking they exist in Pest 3.
- `tests/Pest.php` binds `Tests\TestCase` to `Feature` and `Tags` **only**. `tests/Unit/` runs on bare `PHPUnit\Framework\TestCase` — no container, no facades, no `$this->latte()`. This is deliberate: Unit tests pin pure string rewrites and must stay app-free.
- `tests/Pest.php` also defines global `fixtures_path(...$paths)` and `statamic_package_path(...$paths)` (→ `vendor/statamic/cms`). The `toBeOne` expectation there is dead installer scaffold — ignore it.

## What TestCase boots (tests/TestCase.php)

Per test, Testbench builds a fresh app; `TestCase::getPackageProviders` registers, in order: the addon `Daun\StatamicLatte\ServiceProvider`, `Miko\LaravelLatte\ServiceProvider`, `Statamic\Providers\StatamicServiceProvider`. Order matters — the addon overrides the `latte` view engine in an `app->booted` callback so its engine registration wins.

`TestCase::resolveApplicationConfiguration` then:

1. Sets `view.paths` to `tests/fixtures/views` only.
2. Loads every `vendor/statamic/cms/config/*.php` as the `statamic.*` baseline (`ResolvesStatamicConfig::resolveStatamicConfiguration`) — so a Statamic upgrade transparently updates defaults. Override config **per test** with `config([...])` in `beforeEach`, never by editing global state.
3. Rewires all Stache stores to fixtures (`ResolvesStatamicConfig::resolveStacheStores`): taxonomies, terms, collections, entries, navigation, collection-trees, nav-trees, globals, global-variables, asset-containers, users; plus roles/groups YAML paths, a local `assets` disk rooted at `tests/fixtures/assets-files` (url `/assets`), and `statamic.assets.image_manipulation.secure = false` so Glide URLs are deterministic (no signature — don't re-enable in tests asserting URL substrings).
4. Sets `statamic.users.repository = 'file'`.

**Pro edition and multi-site are COMMENTED OUT** in `resolveApplicationConfiguration` (`statamic.editions.pro`, `statamic.sites.sites`). Pro-gated features are NOT testable out of the box; uncomment those blocks locally (or set the config in a `beforeEach`) when a test needs them. Don't assume pro is on.

`TestCase::setUp` calls `Blueprint::setDirectory(fixtures_path('blueprints'))` — this is what makes `related_page`/`meta.featured_page`/`blocks` augment to entry objects. `TestCase::registerStatamicAddon` hand-seeds `Statamic\Addons\Manifest` because Testbench has no composer addon discovery; if Statamic changes the manifest shape, tests break here first.

## The Concerns (tests/Concerns/)

- `InteractsWithLatteViews::latte(string $template, Arrayable|array $data = [], bool $squish = true): TestView` — the core helper. Writes the template to a unique `tempnam('statamic-latte-')` file with `.latte` extension in `sys_get_temp_dir()`, registers that dir as a view location once, and renders through the full production pipeline (loader → syntax rewrites → Latte compile → engine). Returns `Illuminate\Testing\TestView` (`assertSee`, `assertDontSee`). `Str::squish()`es the template by default — pass `squish: false` to test whitespace behavior (see `tests/Feature/TagTest.php`, `tests/Feature/EngineDelegationTest.php`).
- `MocksFrontendRequests::getFrontendResponse(...$params)` — builds a request via `createRequest($uri = '/', $method = 'GET', ...)` and calls `app(FrontendController::class)->index($request)->toResponse($request)` directly. No router, no middleware: no session side effects, no 404 pages (a missing entry throws). URIs resolve via the pages collection route `{parent_uri}/{slug}`, so `/testable` works.
- `ResolvesStatamicConfig` — the config rewiring described above; used by TestCase, not directly by tests.
- Testbench's own `$this->view('dot.name')` (InteractsWithViews) renders named fixture views from `tests/fixtures/views/` — use it when the scenario needs real files on disk (relative includes, layout lookup), e.g. `tests/Feature/LoaderTest.php`.

## Fixture inventory (tests/fixtures/)

- **pages collection** (`content/collections/pages.yaml`: template `page`, route `{parent_uri}/{slug}`, `layout:` deliberately commented out so the default layout name `layout` applies). Five entries in `content/collections/pages/`: `testable.md` and `testable-with-layout.md` (published; the latter sets `layout: custom-layout`), and `testable-child.md` / `testable-nested.md` / `testable-draft.md` (all `published: false`; child/nested carry relation, group, and grid fields for augmentation tests).
- Blueprint `blueprints/collections/pages/page.yaml`: title, content, slug, parent, related_page (entries max 1), related_pages (entries), meta (group: author + featured_page), blocks (grid: heading). **New fields on fixture entries must be declared here or they won't augment as the intended type.**
- Users: `users/alice@example.com.yaml` (role editor, group editors), `bob@example.com.yaml`, `roles.yaml`, `groups.yaml`.
- Taxonomy `topics` with terms `news`, `tutorials`; nav `main` (Home, About > Team); asset container `assets` with `assets-files/img/example.jpg`; form `forms/contact.yaml` + `blueprints/forms/contact.yaml` (name/email) — the forms dir is wired **per test file**, not globally; `svg/logo.svg`; `vite/build/manifest.json`.
- Views: `layout.latte`, `custom-layout.latte`, `page.latte`, `welcome.latte`, `loader/*`.

To add fixture content: drop YAML/markdown into the matching directory — the Stache scans it, no registration step. For a new collection: yaml in `content/collections/` + entries dir + optionally a blueprint dir.

**INVARIANT: pages keeps exactly 2 published entries.** Dozens of assertions hardcode the count `2` and the sorted string `Testable, Testable With Layout` (CollectionTest, TagTest, SearchTest, GetContentTest...). Adding a published pages entry breaks many tests — add unpublished entries or a new collection instead.

## The CLASSIFY taxonomy (tag-compat tests)

Tag tests document, per tag method, whether it genuinely works through the Latte proxy. 30 of the 38 files in `tests/Tags/` carry `CLASSIFY:` comments (file-header "CLASSIFICATION OVERVIEW" blocks and/or per-test comments). These are load-bearing documentation — keep and extend them. The **full** taxonomy in use:

| Label | Meaning | Example |
|---|---|---|
| `OK` | Works through the proxy as in Antlers | `tests/Tags/LinkTest.php` |
| `INCOMPAT` | Genuinely incompatible (e.g. needs cascade context the harness lacks) | `collection:next/:previous` in `tests/Tags/CollectionTest.php` |
| `INCOMPAT-candidate` | Suspected incompatible, not yet confirmed either way | `tests/Tags/ProtectTest.php` |
| `I6-LIMITED` | Limited because in the proxy `$this->content === ''` and `$this->parse()` returns `[]` | `form:fields`, `form:set` in `tests/Tags/FormTest.php` |
| `BEHAVIOUR SHIFT` | Works but intentionally behaves differently (e.g. `form:errors` returns a boolean gate, not an iterator) | `tests/Tags/FormTest.php` |
| `FIXTURE` | Behavior untestable/empty because fixture data is absent (no cookies, no mix manifest, no errors) | `tests/Tags/MixTest.php`, `tests/Tags/GetErrorTest.php` |
| `N/A` | Classification doesn't apply: tag aborts the HTTP lifecycle, or needs current-URL/structure context that the harness can't meaningfully provide | `tests/Tags/ResponseTagsTest.php`, `tests/Tags/ChildrenTest.php` |

`N/A` and `FIXTURE` dominate the lesser-known files — new compat tests MUST use this exact taxonomy, not invent labels.

Some tests assert a **throw as the pass condition** — this is compatibility documentation, not a bug to fix: `CollectionTest` asserts `{s:collection:next}` throws `Error` (no cascade context); `UnsupportedTagsTest` asserts `{s:loop}`/`{s:increment}`/`{s:dump}` throw `Latte\CompileException` with a steering message (blocklist: `TagNode::$unsupportedTags` in `src/Latte/Extensions/Nodes/TagNode.php`). "Fixing" the addon so these pass is a deliberate feature decision, not a test repair.

### Load-bearing patterns from specific compat files

- `tests/Tags/ResponseTagsTest.php` — `{s:404}` must compile and throw `Statamic\Exceptions\NotFoundHttpException` at runtime; `{s:redirect to: ...}` throws `Illuminate\Http\Exceptions\HttpResponseException`. Asserting the throw IS the pass condition (`expect(fn () => $this->latte(...))->toThrow(...)`).
- `tests/Tags/GetErrorTest.php` — `beforeEach` does `view()->share('errors', new ViewErrorBag)` because Laravel's ShareErrorsFromSession middleware never runs under `$this->latte()`. Without it the tag hits a missing `errors` view variable.
- `tests/Tags/FormTest.php` — distinct, session-based seeding: local helper `seedFormErrors()` puts a `ViewErrorBag` with bag key `form.contact` under session key `errors` (Statamic reads `form.{handle}` via `GetsFormSession`). File-level `beforeEach` wires `config(['statamic.forms.forms' => fixtures_path('forms')])` and flushes `Blink::store()`; a saved submission is deleted in `try/finally` because it writes a real file that would leak across tests.
- `tests/Tags/ChildrenTest.php` — try/catch-tolerant: the tag needs `URL::getCurrent()` + a structure tree, absent in the harness, so tests accept either empty output or a catchable Throwable (never a fatal). `tests/Tags/ParentTest.php` covers the same limitation but asserts empty output directly (`assertSee('[]', false)` around the tag).
- `tests/Tags/DictionaryTest.php` — the only place the built-in dictionary surface is pinned: `{s:dictionary handle: "countries"}`, wildcard `{s:dictionary:countries}`, item fields `$value->name`/`$value->iso2`, timezones.
- `tests/Tags/NoCacheTest.php` — `beforeEach` sets `app.key` and issues a real `$this->get('/')` first, because Statamic's static cache only engages after a request through the cache middleware (which `getFrontendResponse` and `latte()` bypass). `phpunit.xml` sets no APP_KEY.
- `tests/Tags/SvgTest.php` — `usePublicPath(fixtures_path())` + `Svg::disableSanitization()` in `beforeEach`, re-enabled in `afterEach` (static flag survives app rebuild). `tests/Tags/ViteTest.php` uses `usePublicPath(fixtures_path('vite'))`. `tests/Tags/SearchTest.php` builds a real index in a temp dir and `File::deleteDirectory()`s it in `afterEach`. `tests/Tags/GlideTest.php` clears the Stache in `beforeEach`.

## Recipe: add a compat test for a Statamic tag

1. Create `tests/Tags/<TagName>Test.php` — plain Pest file (no namespace, no class); suffix `Test.php` required; automatically bound to `Tests\TestCase`.
2. Header comment: `CLASSIFICATION OVERVIEW` classifying each tag method with the taxonomy above. Model: `tests/Tags/CollectionTest.php` (simple), `tests/Tags/FormTest.php` (per-method nuance).
3. Wrap tests in `describe('<tag>', ...)`. Render with `$this->latte('{s:<tag> param: value}{$value}{/s:<tag>}')`. Idioms: `{$value}` is the injected result, `{sep}...{/sep}` for separators, `as: name` captures into a variable, `{s:<tag> ... /}` self-closing.
4. Add a per-test `// CLASSIFY: <label> — <why>` comment where a method deviates from OK.
5. Needs config or state? File-level `beforeEach` with `config([...])`/facade setup; `afterEach`/`finally` restoring any static or on-disk state (models: FormTest, SvgTest, SearchTest).
6. Needs content? Add fixtures per the inventory rules above — keep pages at 2 published entries, declare new entry fields in the blueprint.
7. Run: `./vendor/bin/pest tests/Tags/<TagName>Test.php`, then `composer test`.

## Recipe: add a feature test (engine behavior)

Create `tests/Feature/<Thing>Test.php` (bound to TestCase). Pick a rendering entry point:

- Inline template pipeline: `$this->latte(...)` — copy `tests/Feature/TagTest.php`.
- Real files needed (includes, layout lookup): add `.latte` fixtures under `tests/fixtures/views/`, render with `$this->view('dot.name')` — copy `tests/Feature/LoaderTest.php`.
- Full page through Statamic routing: `$this->getFrontendResponse('/testable')` + `expect($response->getContent())->toContain(...)` — copy `tests/Feature/LayoutTest.php`. URIs must map to fixture entries via `{parent_uri}/{slug}`.

## Recipe: add a unit test (syntax classes)

Create `tests/Unit/<Thing>Test.php`. It is NOT bound to TestCase — no app, no facades, no `$this->latte()` (calling them throws confusing errors). Call statics directly (`TagExpressionSyntax::rewrite(...)`, `TagMethodSyntax::rewrite(...)`), assert exact output strings, use Pest datasets (`->with([...])`) and `->throws(Latte\CompileException::class, 'message fragment')`. Copy `tests/Unit/TagExpressionSyntaxTest.php`. If a "unit" test needs the container, it belongs in Feature/Tags instead — don't bind Unit in `tests/Pest.php` casually.

## Invariants

- Never add a third published entry to the pages collection — hardcoded counts break everywhere.
- Never let Unit tests touch the app — Pest.php's binding scope is deliberate.
- Never re-enable Glide URL signing (`image_manipulation.secure`) in tests asserting URLs.
- Always restore mutated statics and delete written files (`afterEach`/`finally`): the app rebuilds per test, but statics on Statamic classes and files on disk survive.
- Keep CLASSIFY comments accurate when changing tag behavior — they are the compat contract, and README's Forms section mirrors the FormTest classifications.

## Pitfalls

- **`assertSee` HTML-escapes by default.** Assert raw markup with the second arg: `assertSee('<form', false)`. Getting this wrong produces baffling false failures.
- **`latte()` squishes whitespace by default** — pass `squish: false` when whitespace matters.
- **State leakage:** session/Blink normally reset with the per-test app, but Statamic caches form resolution in Blink (FormTest flushes defensively), Stache stores can persist across quick successive runs (`Statamic\Facades\Stache::clear()` in `beforeEach`, see GlideTest), and disk state (form submissions, search indexes) genuinely leaks without explicit cleanup. Phantom submissions/search hits = a prior test saved without cleanup.
- **`getFrontendResponse` bypasses middleware** — no session sharing, no exception→404 rendering, static cache inert (see NoCacheTest workaround).
- **Temp files accumulate:** `latte()` leaves `statamic-latte-*` stubs + `.latte` files in the system temp dir; compiled PHP lands in `vendor/orchestra/testbench-core/laravel/storage/framework/views` as `-latte--<hash>.php`. Harmless, safe to clean. To debug generated code after a failing run: `ls -t` that views dir and read the newest compiled file.
- **Compile vs runtime failures:** `Latte\CompileException` = template never compiled (syntax sugar, unsupported tag); PHP `Error`/`ErrorException` at render = compiled fine, tag misbehaved at runtime. See UnsupportedTagsTest vs CollectionTest for the assertion pattern of each.
- **Empty tag output is often documented, not broken** — check the CLASSIFY notes in the tag's test file before debugging the engine.
- **Relations not augmenting** (raw IDs instead of titles): field missing from `tests/fixtures/blueprints/collections/pages/page.yaml`, or `Blueprint::setDirectory` no longer pointed at fixtures.
- `phpunit.xml` coverage source includes `./app`, which doesn't exist — only `./src` matters.

## Verifying a change

```sh
composer test                                    # full suite (= ./vendor/bin/pest)
./vendor/bin/pest tests/Tags/CollectionTest.php  # single file
./vendor/bin/pest --filter="renders published"   # single test by name
composer test:coverage                           # with coverage
composer lint && composer analyse                # pint --test, phpstan
```

Baseline: 378 tests pass in roughly 19s. If your change makes the suite much slower, investigate — you probably introduced per-test disk churn or an unintended warm-up request.

## Related skills

orientation, data-layer, tag-bridge, extensions-and-nodes, caching, template-syntax, debugging, quality-gates
