---
name: debugging
description: Diagnosing broken Latte templates in this addon — wrong output, always-true conditionals, silent tag output, stale compiled templates, "class not found" after renames, fragments that never cache, tests that fail only in the full suite. Load for any "template renders wrong / errors / caches weirdly" task before touching src/.
---

# Debugging statamic-latte

Almost every bug in this addon lives in one of four places: the source rewrite (before Latte parses), the compiled PHP (what Latte emitted), the runtime data layer (Content/Deferred wrappers), or stale compile caches. The single most effective technique is reading the compiled template — it shows you exactly what the rewrite produced and what the nodes emitted, with no guessing.

## When to use this skill

- A template renders wrong output, empty output, or throws at render time.
- `{if}` / `{foreach}` / modifiers behave differently than in Antlers.
- Changes to a `.latte` file or to addon PHP don't show up.
- `{cache}` / `{nocache}` fragments misbehave.
- A test passes alone but fails in the full suite.

## Core technique: inspect the compiled template

**Where compiled PHP lives.** Latte compiles into `config('latte.compiled') ?? config('view.compiled')` (vendor/miko/laravel-latte/src/ServiceProvider.php). In a real site that is `storage/framework/views`. Under Testbench (the test suite) it is `vendor/orchestra/testbench-core/laravel/storage/framework/views`.

**Filename mapping.** Files are named `{name-fragment}--{10-char-hash}.php` (built in `Latte\Runtime\Cache::generateFilePath`); the fragment comes from the last path segments of the template, and the hash mixes the engine configuration signature with `LaravelViewLoader::getUniqueId` (in practice the absolute file path). Inline test templates written by the `latte()` helper land in `sys_get_temp_dir()` with random names, so their compiled files often collapse to `-latte--{hash}.php`. Don't guess from filenames — every compiled file has a header comment mapping it back:

```php
/** source: /private/var/folders/.../statamic-latte-XXXX.latte */
final class Template_0ee5f82198 extends Latte\Runtime\Template
```

`grep -rl 'source: .*yourtemplate' <compiled-dir>` finds the file. Sort by mtime (`ls -t`) to find the one your last test run produced.

**How to read compiled output.**
- `$ʟ_`-prefixed variables (the character is U+029F, not a plain `l`) are compiler internals: `$ʟ_result`, `$ʟ_iterable`, `$ʟ_output`. User template variables arrive via `extract($ʟ_args)`.
- Project classes appear as fully-qualified string-baked names (`\Daun\StatamicLatte\Latte\Support\Tags::fetch(...)`) because compiled templates have no `use` statements.
- Echoes go through escapers: `echo LR\HtmlHelpers::escapeText($value->title)`. A raw `echo` means `|noescape` or a node's own `print()` output.
- `/* pos 3:15 */` comments map each statement back to template line:column.
- Temp extracted views from `{antlers}`/`{nocache}` sit in the same directory as `latte-tag-content-{sha1}.antlers.html` / `.latte` — open them to see exactly what raw text the node captured.

**Forcing recompilation.** Delete the compiled files (in a real site: `php artisan view:clear`, which also removes the temp extracted views). Latte's auto-refresh (on when `app.debug`, override via `config('latte.auto_refresh')`) uses an xxh128 refresh signature stored in a `.lock` file next to each compiled template (`Latte\Runtime\Cache::loadOrCreate`); the signature covers the Latte version, the loader's `getContent` output (post-rewrite source), and extension file mtimes. Consequences:

- Editing a `.latte` file recompiles it automatically (in debug).
- **Changing rewrite logic in `TagMethodSyntax`/`TagExpressionSyntax` does NOT invalidate existing compiled templates** — the template source is unchanged. Delete the compiled dir when iterating on rewrite rules.
- Adding/removing/reordering extensions changes the configuration hash, so old files are orphaned, not reused (dir grows, never conflicts).

## Seeing the source rewrite

`TagMethodLoader` rewrites template source BEFORE Latte parses it: `{s:tag:method ...}` is lowered to `{s:tag __sl_tag: "tag:method", ...}` and `(s:...)` expressions to `s('...', [...])` calls, skipping `{* comments *}` and `{antlers}` islands (`TagMethodLoader::PROTECTED`, `TagMethodLoader::rewrite`). To isolate "rewrite bug vs parse bug":

```php
// Post-rewrite source Latte actually compiles:
app(\Latte\Engine::class)->getLoader()->getContent($absoluteTemplatePath);
// Compare against file_get_contents($absoluteTemplatePath).

// Compiled PHP without executing:
app(\Latte\Engine::class)->compile($nameOrPath);
```

The rewrites are also pure string functions callable with zero bootstrapping: `TagMethodSyntax::rewrite($src)`, `TagExpressionSyntax::rewrite($src)`, and `TagArguments::escapeNestedKeys($paramText)` — see tests/Unit/TagMethodSyntaxTest.php and tests/Unit/TagExpressionSyntaxTest.php for the pattern.

## Symptom → cause → check

| Symptom | Likely cause | How to check |
|---|---|---|
| `{if $related}` is always true on a relationship field | `Deferred` is an object, therefore always truthy — **by design**: only NON-empty relationships are deferred (`Content::wrapTopLevel` requires `isRelationship()` && `!empty($value->raw())`), so truthiness is correct when the invariant holds | If an empty relationship shows truthy, something created a Deferred outside `wrapTopLevel` or the raw value isn't actually empty. Dump `get_class($x)` and `$x->source()->raw()`. Contract: template-syntax skill; internals: data-layer skill |
| `{foreach}` over a relationship fails or iterates field names | Shape mismatch: `max_items: 1` fields materialize to a **single Content**, lists to a **plain array of Content** (`Deferred::materialize` docblock). Iterating a Content iterates its FIELDS | Check the blueprint's `max_items`. `{foreach $one_related as $x}` on a single Content loops over field values — access `$related->title` directly instead |
| A modifier behaves differently than in Antlers | Modifiers get an **empty context** — `ModifierExtension::applyModifier` calls `($this->loader->load($name))($value, $args, [])`. Deliberate (fix/modifier-context, released 1.2.1): modifiers run context-free in Latte | If the modifier reads its third `$context` param, it sees `[]`. Don't "fix" by passing the cascade — that changes documented behavior |
| `{cache}` fragment never caches / re-renders every request | `CacheNode::print` emits `if ($ʟ_output = $ʟ_store->get($ʟ_key))` — a **truthiness** check. Output of `''` or `'0'` is a permanent cache miss | Also verify `Cache::enabled()` preconditions first: GET request, `statamic.system.cache_tags_enabled`, `if:` param not false. See caching skill |
| "Class not found" for an addon class in templates | Stale compiled templates bake FQCNs as string literals; IDE renames don't touch them and the refresh signature doesn't notice addon renames either | Clear the compiled dir. Emitting files: `TagNode`, `CacheNode`, `SectionNode`, `YieldNode`, `AntlersNode` (all under src/Latte/Extensions/Nodes/), `AttributeNormalizationExtension`; `NocacheNode` bakes a Statamic FQCN. After any rename: `grep -rn 'StatamicLatte' src/ \| grep "'"` and keep a deprecation shim like `src/Data/Normalizer.php` (kept until 3.0 exactly for this) |
| `{s:tag}` returns nothing | Bug in one of four stages: (1) loader rewrite, (2) Latte parse/`TagNode` emission, (3) runtime fetch, (4) result wrap/stringify | (1) dump `getContent()`; (2) read the compiled file; (3) breakpoint `Tags::fetchTag` — inspect `$tag->fetch()` result; (4) remember `Content::wrap` and `Tags::stringifyResult` print booleans as `''` and non-stringables as `''`, never fataling. See tag-bridge skill |
| Edits to a `.latte` file don't appear | Compiled-template staleness: `auto_refresh` is off (production default when `app.debug` is false), or you edited rewrite/extension logic (see above) | Delete compiled files / `php artisan view:clear`; confirm `config('latte.auto_refresh')` / `app.debug` |
| `{nocache}` hole never filled on cached pages | `{nocache}` only works with **application-level (half measure)** static caching. Full file-based caching serves files without booting PHP, and the JS replacement mechanism is not implemented (README "Limitations") | Check `statamic.static_caching.strategy`. In tests, NoCache machinery is inert without an `app.key` and a request through the cache middleware — see tests/Tags/NoCacheTest.php `beforeEach` |
| Test passes alone, fails in the full suite | Static state leakage across tests in one process: Cascade sections, Laravel view-factory sections, Antlers `LiteralReplacementManager`, Blink slots | Copy the reset trio from tests/Tags/SectionTest.php `beforeEach`: `Cascade::instance()->clearSections()`, `app('view')->flushSections()`, `LiteralReplacementManager::resetLiteralState()`. Stache is rewired to fixtures in tests/TestCase.php — tests must not mutate fixture content |
| Literal `\x00@latte-yield:...` tokens in output | `Sections::resolve` never ran on that chunk: render bypassed `NormalizingEngine::get`, or a `{cache}` block captured the raw token | Verify `View::getEngineResolver()->resolve('latte')` is `NormalizingEngine`; keep `{yield}` outside `{cache}` |

## Reproduce as a test FIRST

Before diagnosing in a browser, pin the bug with the inline helper from tests/Concerns/InteractsWithLatteViews.php (details in the testing skill):

```php
it('reproduces the bug', function () {
    $this->latte('{$x|upper}', ['x' => 'ok'])->assertSee('OK');
});
```

`latte()` writes a temp `.latte` file and renders through the FULL real pipeline — loader rewrites, extensions, NormalizingEngine — so it reproduces anything except routing/cascade issues (use `$this->getFrontendResponse('/testable')` from tests/Concerns/MocksFrontendRequests.php for those). It `Str::squish()`es the template by default; pass `squish: false` for whitespace-sensitive assertions. For data-layer bugs, build the exact boundary input: `$this->latte('{$rel|length}', ['rel' => $entry->augmentedValue('related_pages')])` — the pattern tests/Feature/DeferredTest.php uses. Run one file with `./vendor/bin/pest tests/Feature/DeferredTest.php` or `composer test -- --filter=Deferred`.

## Runtime tag failures

The runtime path is `Tags::fetch` → `Tags::run` → `Tags::fetchTag` in src/Latte/Support/Tags.php. Breakpoint or `dump()` inside `fetchTag` — it is the single choke point for every `{s:...}` block and `(s:...)` expression. Things to know while there:

- **Blink paginator hand-off**: Statamic flattens paginated results to a plain array and stashes the real paginator in `Blink` under `'tag-paginator'`. `fetchTag` snapshots the existing value before `$tag->fetch()` and recovers only a new paginator instance, leaving the shared slot available to addon tags and Statamic's static-cache middleware. If you invoke Statamic tags without going through `Tags::fetch`, pagination silently degrades to a keyed array — and reading Blink without comparing before/after identity can mistake an earlier tag's paginator for the current result.
- A `BadMethodCallException` is rethrown as `{s:users:count}: 'count' is not a valid method of the users tag.` — method-name smuggling worked, Statamic rejected the method.
- `TagNotFoundException` from the expression form means the name after `s:` is wrong (expression rewrite is a catch-all with runtime resolution).
- "Unexpected tag" at compile time for `{s:x}` means the tag wasn't in `app('statamic.tags')` when `TagExtension` was constructed at boot. The inline `(s:x ...)` form still works — use it as a probe.
- Quick value dumps inside templates: `{dump $var}` (provided by Miko's extension; `{s:dump}` is deliberately blocked in `TagNode::$unsupportedTags` in favor of it).

Data-layer escape hatches for dumping: `$content->source()` (raw Augmentable/Values/array), `$deferred->source()` (the underlying `Statamic\Fields\Value`), `Content::unwrap($x)` (peel a whole structure). A bare Content dumps as an empty-looking object because everything is lazy.

## PHPStan as a debugging aid — and its limits

`composer analyse` (phpstan via larastan, level 5, `--memory-limit=2G`) catches renamed/missing symbols, wrong signatures, and impossible branches cheaply — run it whenever a bug smells like a refactor casualty. Its blind spots here:

- **It cannot see into compiled-code strings.** Every FQCN emitted by a node's `print()` is a string literal; a rename passes PHPStan and fatals at render time. Grep is the tool, not PHPStan.
- Level 5, no generics annotations — mixed-heavy code (tag results, `Content::wrap` returns) is largely unchecked.
- Sanctioned ignores exist: phpstan.neon suppresses two identifiers for the Latte version-compat branch in `ExtractsToTemporaryView`, and Content.php carries one `@phpstan-ignore method.notFound` for the upstream `Augmented->keys()` interface gap. Don't "fix" those.

## Escalation map

When the symptom localizes to a subsystem, load the sibling skill and its design doc:

| Subsystem | Skill | Deeper reference |
|---|---|---|
| Content/Deferred/Resolver, wrap/unwrap boundaries | data-layer | docs/plan-data-layer-rebase.md (design + rejected alternatives), docs/report-data-layer-rebase.md (what shipped, review fixes) |
| `{s:...}` tags, `(s:...)` expressions, params, pagination | tag-bridge | tests/Feature/TagTest.php as executable spec |
| `{antlers}`, `{section}`/`{yield}`, `{slot}`, n:attr, layout resolution, custom nodes | extensions-and-nodes | docs/antlers-blade-bridge-research.md (cross-engine bridge research) |
| `{cache}`, `{nocache}`, static caching interplay | caching | README "Caching" + "Limitations" sections |
| Intended template semantics (what SHOULD happen) | template-syntax | README |
| Test harness, fixtures, helpers | testing | tests/TestCase.php, tests/Pest.php |
| Boot/wiring ("nothing installs at all") | orientation | src/ServiceProvider.php; in tests check `TestCase::registerStatamicAddon` — without the Manifest entry, `bootAddon()` silently no-ops |

## Verifying a fix

1. The reproducing test you wrote first now passes: `./vendor/bin/pest <file>`.
2. Full suite: `composer test` (378 tests, ~19s). If section/cache tests fail only in the suite, revisit the state-leakage row above.
3. `composer analyse` and `composer lint` stay green (see quality-gates skill).
4. If you touched anything that emits compiled code (any `print()` method, rewrite classes, extension list), delete the Testbench compiled dir once and re-run to prove the fix isn't riding on stale caches.

## Related skills

orientation, data-layer, tag-bridge, extensions-and-nodes, caching, template-syntax, testing, quality-gates
