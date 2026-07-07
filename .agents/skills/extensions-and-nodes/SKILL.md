---
name: extensions-and-nodes
description: Writing or modifying Latte extensions and compiler nodes in src/Latte/Extensions — layout auto-resolution, section/slot/yield, {antlers} blocks, modifiers-as-filters, n:attr normalization, resolve() helpers. Use when adding a new template tag, filter, function or compiler pass, when a tag compiles to wrong PHP, or when bumping the latte/latte dependency.
---

# Latte Extensions and Compiler Nodes

This addon makes Latte a first-class Statamic engine through nine `Latte\Extension` subclasses registered on the shared `Latte\Engine` singleton. Extensions contribute tags (backed by compiler nodes that emit PHP), filters, functions, compiler passes, and providers. This skill covers everything except the `(s:...)` tag bridge (`TagExtension`/`TagNode` — see tag-bridge) and the `{cache}`/`{nocache}` internals (see caching).

## When to use this skill

- Adding a new template tag, `n:` attribute, filter, function, or compiler pass.
- Changing layout resolution, section/yield behavior, `{antlers}` blocks, `{slot}`, modifier filters, or `n:attr` handling.
- Debugging wrong compiled PHP output from a custom tag.
- Supporting a new Latte version (compiled-output drift, version branches).

## Extension anatomy and registration

An extension extends `Latte\Extension` and overrides any of:

| Method | Returns | Example in this repo |
|---|---|---|
| `getTags()` | `['name' => [NodeClass::class, 'create']]` | `SectionExtension`, `AntlersExtension` |
| `getFilters()` | `['name' => callable]` | `ResolverExtension`, `ModifierExtension` |
| `getFunctions()` | `['name' => callable]` | `ResolverExtension` |
| `getPasses()` | `['unique-name' => callable(TemplateNode)]` | `AttributeNormalizationExtension` |
| `getProviders()` | `['name' => value]` | `LayoutExtension` (`coreParentFinder`) |

Registration point: `ServiceProvider::$defaultExtensions` (public static array of 9 classes: Antlers, AttributeNormalization, Cache, Layout, Modifier, Resolver, Section, Slot, Tag). `ServiceProvider::installExtensions()` iterates it and calls `$engine->addExtension(new $extension($engine))` on the container's `Latte\Engine` singleton during `bootAddon()`.

Facts you must respect:

- **Every extension is constructed with the Engine as first arg**, but only `ModifierExtension` declares a parameter for it. `TagExtension` also declares a constructor — zero-parameter, so the Engine arg is silently discarded (PHP ignores surplus args not declared in the signature; same for constructor-less classes). If you add a constructor to any other extension, its first parameter will silently receive the `Latte\Engine`.
- **Order matters for providers.** `Latte\Engine::addExtension` merges `getProviders()` by overwriting keys. Addon extensions are registered after Latte core and miko/laravel-latte, so `LayoutExtension`'s `coreParentFinder` deliberately overwrites Miko's config-based one (registered in `vendor/miko/laravel-latte/src/ServiceProvider.php`). Put a new extension after any extension whose same-named provider/filter it must override.
- `addExtension()` eagerly consumes `getFilters()`/`getFunctions()`/`getProviders()` at boot; `getTags()` and `getPasses()` are re-read from each registered extension on every compile. `ModifierExtension` therefore only sees modifiers registered before the addon boots (its `getFilters()` runs once at `addExtension()` time).
- New classes in `$defaultExtensions` are automatically covered by `tests/Feature/ServiceProviderTest.php` ("installs the default extensions" asserts every listed class is on the engine).

## Node anatomy

A node extends `Latte\Compiler\Nodes\StatementNode` with three parts (model: `src/Latte/Extensions/Nodes/SectionNode.php`, the simplest):

1. **`create(Tag $tag[, TemplateParser $parser]): \Generator`** — static factory called at parse time. Assign `$tag->node = new self`, parse arguments from `$tag->parser`:
   - `parseUnquotedStringOrExpression()` — single name accepting bare words, quoted strings, and variables (`SectionNode`, `YieldNode`; dynamic names work for free).
   - `parseArguments()` — named/positional params as an `ArrayNode` (`CacheNode`).
   - `$tag->void` is true for the self-closing form `{yield 'x' /}` — return early, skip the body (`YieldNode::create`).
   - `[$node->content] = yield;` receives the parsed body for paired tags.
2. **`print(PrintContext $context): string`** — emits compiled PHP via `$context->format()`. Placeholders: `%node` interpolates a child node, `%dump` a literal PHP value, `%line` the source position.
3. **`&getIterator(): \Generator`** — yields every child node (name expression, content). Compiler passes traverse through this; forgetting a child hides it from passes like `AttributeNormalizationExtension::unwrapPass`.

Two hard rules for `print()`:

- **Prefix compiled-code locals with `$ʟ_`** (e.g. `$ʟ_result`, `$ʟ_output` in `TagNode`/`CacheNode`). Compiled templates share one variable scope with user template variables; the `ʟ` prefix is Latte's convention for internals and prevents collisions with `{var $output = ...}` in templates.
- **Use fully-qualified class names as string literals.** Compiled templates are plain PHP with no `use` statements, so `print()` output must reference `\Daun\StatamicLatte\...` FQCNs inside format strings/heredocs. These are *strings* — IDE rename refactorings will NOT update them. After renaming any class under `Daun\StatamicLatte`, run:

  ```sh
  grep -rnF '\Daun\StatamicLatte' src/
  ```

  Six files currently bake FQCN strings into compiled output: `AttributeNormalizationExtension` (Content::unwrap), `AntlersNode` (Content::unwrap), `SectionNode`/`YieldNode` (Sections), `CacheNode` (Cache), `TagNode` (Tags, Content). `NocacheNode` additionally bakes the Statamic FQCN `"Statamic\StaticCaching\NoCache\BladeDirective"`. Stale compiled views keep old FQCNs too — clear the compiled dir after renames (same rule documented in the data-layer skill).

## ExtractsToTemporaryView concern

`src/Latte/Extensions/Nodes/Concerns/ExtractsToTemporaryView.php` — shared by `AntlersNode` and `NocacheNode` for tags whose body must escape Latte compilation entirely (raw text handed to another engine):

- `disableParserForTag(Tag, TemplateParser)` — `$lexer->setSyntax('off', ...)` disables `{}` parsing until the matching end tag; `$parser->setContentType(ContentType::Text)` disables HTML processing. Prior state is saved in **static `WeakMap`s keyed by the `Tag`** (`$lexerDelimiters`, `$contentTypes`) so nested/concurrent tags don't clobber each other and entries are GC'd with their tags.
- `restoreParserForTag(Tag, TemplateParser)` — restores state. Must ALWAYS be called after the `yield` that consumed the body; skipping it corrupts parsing of everything after the tag.
- **Latte version branch:** both methods check `Engine::VersionId < 30014`. Pre-3.0.14, lexer open/close delimiters must be saved/restored manually via the `$lexerDelimiters` WeakMap; 3.0.14+ uses `$lexer->popSyntax()`. These are the only `VersionId` branches in `src/`.
- `saveContentToView(?string $extension)` — writes `NodeHelpers::toText($this->content)` to `config('view.compiled')/latte-tag-content-{sha1(content)}.{ext}` (content-addressed dedup: skips write if the file exists) and returns `'statamic-latte-temp::latte-tag-content-{sha1}'`. The `statamic-latte-temp` namespace is mapped to `config('view.compiled')` by `ServiceProvider::registerViewNamespace()` — the temp view is unresolvable without it.
- `$viewFileExtension` defaults to `'latte'` (NocacheNode: region re-enters the Latte engine); `AntlersNode::create` overrides it to `'antlers.html'` so the temp view renders through Statamic's Antlers engine.

Any node rendering a temp view as a standalone view MUST inject `'__layout_parent' => $this->getName()` into its data — see LayoutExtension below.

## The shipped extensions

### LayoutExtension — auto-layout from entry data

`src/Latte/Extensions/LayoutExtension.php` registers the `coreParentFinder` provider — the hook Latte core calls **only when a template has no `{extends}`/`{layout}` of its own**. Resolution order, exactly:

1. Explicit `{layout ...}`/`{extends ...}` in the template always wins (Latte never calls the finder).
2. Includes/embeds are skipped: the closure returns nothing when `$template->getReferenceType()` is set.
3. `LayoutExtension::resolveLayout` returns `null` if `$params['__layout_parent']` is set — temp views from `{antlers}`/`{nocache}` must not be re-wrapped in the page layout (duplicated chrome / recursion otherwise).
4. Otherwise returns `$params['current_layout'] ?? null`. Statamic core injects `current_layout` into every frontend cascade (`Statamic\View\View`), so `layout: foo` on an entry or collection picks `foo.latte` automatically.

Change layout rules only in `resolveLayout`; keep the `__layout_parent` early-return and the `getReferenceType()` guard. Tested by `tests/Feature/LayoutTest.php` via real frontend responses (`/testable`, `/testable-with-layout`).

### SectionExtension + Sections runtime — cross-engine content bus

`SectionExtension` maps `{section}` → `SectionNode`, `{yield}` → `YieldNode`. Interops with Antlers `{{ section }}/{{ yield }}` and Blade `@section/@yield`.

- `SectionNode::print` — wraps the body in `ob_start(fn() => '')` / `Sections::store($name, ob_get_clean())`.
- `Sections::store` (`src/Latte/Support/Sections.php`) writes to **`Cascade::instance()->sections()` as primary** (the reliable cross-engine store Antlers reads) and best-effort mirrors into the Laravel view factory for Blade `@yield`. Cascade must stay primary: the factory flushes sections after each render.
- `YieldNode::print` — a yield cannot read inline (a layout's `<head>` renders before a deep body partial defines its section), so it echoes `Sections::placeholder($name[, $default])`, which returns a unique token `"\x00@latte-yield:{16 hex}\x00"` and records `{name, default}` in `static::$pending`.
- `NormalizingEngine::get` (`src/Latte/NormalizingEngine.php`) drives the lifecycle: `Sections::beginRender()` (depth++), render, `Sections::resolve($output)` substitutes tokens, `Sections::endRender()` in a `finally` (depth--). `resolve()` replaces **only tokens present in this output chunk** and forgets just those; `endRender()` clears `$pending` **only at depth 0**. Both rules exist because `{nocache}`/`{antlers}` re-enter `NormalizingEngine::get` recursively — violating either eats the parent template's yields.
- Literal `\x00@latte-yield:` tokens in output mean `resolve()` never ran on that chunk — the render bypassed `NormalizingEngine`, or the token was captured mid-render (e.g. a `{cache}` block around a `{yield}` caches the raw token; keep yields outside caches).

Tested by `tests/Tags/SectionTest.php` in both orders within Latte and in both Latte↔Antlers directions; the Blade `@yield` mirror in `Sections::store` is NOT covered by tests — verify manually if you touch it. Note its mandatory `beforeEach` reset trio (`Cascade::instance()->clearSections()`, `app('view')->flushSections()`, `LiteralReplacementManager::resetLiteralState()`); copy it into any new section test or static state leaks across tests.

### SlotExtension — {slot} as a pure {block} alias

`src/Latte/Extensions/SlotExtension.php` maps `'slot' => [\Latte\Essential\Nodes\BlockNode::class, 'create']` — zero custom code (introduced in PR #15, `feat/slot-alias`). Because the node IS a `BlockNode`, it passes `{embed}`'s instanceof content check, and `n:slot`/`n:inner-slot` come free (Latte auto-derives `n:` forms). Keep it a zero-logic alias; a custom node class breaks both properties. `tests/Feature/SlotTest.php` asserts byte-equality with `{block}` output.

### AntlersExtension — inline {antlers} blocks

`AntlersNode` (`src/Latte/Extensions/Nodes/AntlersNode.php`), using `ExtractsToTemporaryView`:

- `create()` sets `viewFileExtension = 'antlers.html'`, throws `CompileException` on any arguments (`"Unexpected arguments in {antlers}"`) and on `n:antlers` (`'Attribute n:antlers is not supported.'`) — the body is raw text, so arguments are meaningless and an `n:` form can't wrap raw-text capture around an element. Wraps the body `yield` in `disableParserForTag`/`restoreParserForTag`, so Latte syntax inside `{antlers}` stays literal text.
- `print()` emits `echo view($tempView, \Daun\StatamicLatte\Data\Content::unwrap(["__layout_parent" => $this->getName()] + get_defined_vars()))->render()`. Every Latte-scope variable crosses into Antlers, and `Content::unwrap` peels Content/Deferred wrappers first — **Antlers does its own augmentation and cannot traverse our wrappers**. Sections written by the inner Antlers land in the shared Cascade (that's the antlers→latte interop in SectionTest).

### ModifierExtension — Statamic modifiers as Latte filters

`src/Latte/Extensions/ModifierExtension.php` — the only extension whose constructor accepts the `Latte\Engine`. `getFilters()` maps every modifier in `app('statamic.modifiers')` to a closure, `->except(array_keys($this->latte->getFilters()))` — **already-registered Latte filters are never overwritten**; core/user filters win over same-named modifiers by design (pinned by `tests/Feature/ModifierTest.php` "preserves existing filters").

`applyModifier()` calls `Content::unwrap()` on the value AND every argument (modifiers predate the Content wrapper and expect plain values/arrays/augmentables), then invokes the modifier with `[]` as the context argument. The empty context is a deliberate contract from PR #7 (`fix/modifier-context`, released 1.2.1): modifiers run context-free in Latte, unlike Antlers. Do not "helpfully" pass cascade data — it changes modifier behavior.

### AttributeNormalizationExtension — n:attr Content unwrapping

`src/Latte/Extensions/AttributeNormalizationExtension.php` — a compiler pass, not a tag. `getPasses()` registers `'statamic-latte-attribute-normalization' => unwrapPass`, which uses `NodeTraverser` to find every `Latte\Essential\Nodes\NAttrNode` and rewraps each argument expression in an `AuxiliaryNode` printing `\Daun\StatamicLatte\Data\Content::unwrap(<expr>)`. Why: Latte's `n:attr` runtime does `is_array()` and **silently drops objects** — `<div n:attr="$attrs">` with a Content-wrapped array would render no attributes. `unwrap()` is a no-op for scalars, so keyed forms (`n:attr="href: $url"`) are unaffected. Pinned by `tests/Feature/NAttributeTest.php` ("spreads an associative array passed as a Content object").

Note: the type-aware "smart attribute" rendering itself (booleans, null removal, array class/style, `data-*` JSON, ARIA) is **native Latte 3.1 behavior**, not addon code — `tests/Feature/SmartAttributesTest.php` pins it (and asserts Latte >= 3.1 is installed) because Statamic tag subexpressions flow into those constructs. The pass targets only `NAttrNode`; other object-hostile runtime spots would need their own pass.

### ResolverExtension — resolve()/r() and |resolve

`src/Latte/Extensions/ResolverExtension.php`: `getFunctions()` maps both `resolve` and `r` to `Daun\StatamicLatte\Data\Resolver::actual`; `getFilters()` maps `resolve` to `Resolver::drill` (`{$author|resolve:'name'}` drills dot-notation keys). Escape hatches for raw `Value`/`LabeledValue`/query-builder objects in expressions. `Resolver` itself is owned by the data-layer skill.

### CacheExtension — {cache}/{nocache}

Registers `CacheNode` and `NocacheNode`. Internals (key derivation, scopes, static-cache hole punching, the double-body-emission quirk) are covered by the **caching** skill. Structurally relevant here: `NocacheNode` uses `ExtractsToTemporaryView` with the default `.latte` extension and injects `__layout_parent`, same as `AntlersNode`.

## Interaction rules — the NormalizingEngine boundary

- Data enters Latte through `NormalizingEngine::get` (`Content::wrapAll`) and yields are resolved there (`Sections::resolve` + depth counter). Rendering a `.latte` template via raw `$latte->renderToString()` bypasses both: template values arrive unwrapped (property access breaks) and `{yield}` outputs literal `\x00` tokens. Always render through the view factory / `NormalizingEngine`. (Exception: `tests/Feature/SlotTest.php`'s `renderLatte()` helper deliberately uses a `StringLoader` + `renderToString` — fine there because slots need neither wrapped data nor Sections.)
- Every value crossing OUT of Latte into Statamic-native code must be `Content::unwrap()`ed. Existing boundaries: `ModifierExtension::applyModifier`, `AttributeNormalizationExtension::unwrap`, `AntlersNode::print`. A new extension handing template variables to modifiers, tags, or Antlers must do the same, or wrapper objects leak into code expecting arrays/augmentables.
- Adding/removing/reordering extensions changes Latte's configuration signature, so all compiled templates are silently orphaned and recompiled — expected, but clear `storage/framework/views` when output looks stale during development (auto-refresh does not track changes to extension *logic*).

## Recipe: add a new template tag / n:attribute

1. **Node**: create `src/Latte/Extensions/Nodes/YourNode.php` extending `StatementNode`. Copy `SectionNode` (single-name arg + body) or `CacheNode` (named args + runtime support class). For raw-text bodies, copy `AntlersNode` and follow the ExtractsToTemporaryView section above. Latte auto-derives the `n:yourtag` attribute form unless `create()` rejects `$tag->isNAttribute()`.
2. **Runtime logic**: if the compiled code needs more than a few lines, put a static support class in `src/Latte/Support` (like `Sections`/`Cache`) and call it by FQCN string from `print()`.
3. **Extension**: add the tag to an existing extension's `getTags()` if it belongs to that family, else create `src/Latte/Extensions/YourExtension.php` and append it to `ServiceProvider::$defaultExtensions`.
4. **Inspect compiled output**: render once via `$this->latte('...')` in a test, then read the newest `*.php` in the compiled dir and check the emitted PHP (`$ʟ_` locals, FQCNs). See the debugging skill for the full workflow.
5. **Tests**: Pest file under `tests/Feature/` or `tests/Tags/`. Templates to copy: `tests/Feature/NAttributeTest.php` (uses `$this->latte(...)->assertSee(...)` / `->assertSeeInOrder(...)`; pass `false` as the assertion's second (escape) argument to match raw HTML), `tests/Feature/SlotTest.php` (StringLoader helper for multi-template embed/layout scenarios without fixtures), `tests/Tags/AntlersTest.php` (compile-error assertions: `expect(fn () => $this->latte(...))->toThrow(CompileException::class, '...')`).
6. Run `composer test` (full suite, ~19s), `composer analyse`, `composer lint`.

## Recipe: support a new Latte version

1. Version branches live ONLY in `ExtractsToTemporaryView` (`Engine::VersionId < 30014`, twice). Check whether the new version changes lexer syntax handling; if the addon drops pre-3.0.14 support, both branches and the `$lexerDelimiters` WeakMap can go.
2. `tests/Feature/SmartAttributesTest.php` asserts `Engine::VERSION >= 3.1` — bump if the floor rises.
3. Find compiled-output drift: run `composer test`. Failures cluster where Latte changed node classes or compiled-PHP conventions. Prime suspects: `AttributeNormalizationExtension` (targets the concrete class `Latte\Essential\Nodes\NAttrNode` — verify it still exists and its `args->items` shape), `SlotExtension` (aliases `Latte\Essential\Nodes\BlockNode::create` — verify signature), any `parseUnquotedStringOrExpression`/`parseArguments` call sites.
4. Diff compiled output before/after the bump for a template exercising each tag (compiled dir inspection — debugging skill). Watch for renamed Latte-internal `$ʟ_` helpers and changed escaper calls.
5. CI matrix is PHP 8.3–8.5 × Laravel 12/13 × Statamic 6; Latte comes transitively via `miko/laravel-latte ^3.0`, so a Latte floor bump means checking miko's constraint in `composer.json`.

## Invariants

- Never construct a second `Latte\Engine` — always resolve `Latte\Engine::class` from the container; loader, extensions, and NormalizingEngine all share one singleton.
- `disableParserForTag` must be paired with `restoreParserForTag` around the body `yield`.
- Temp-view renders must carry `__layout_parent`; `Sections::store` must keep Cascade primary; `resolve()`/`endRender()` must keep the replace-per-chunk / clear-at-depth-0 rules.
- `{slot}` stays a direct alias of `BlockNode::create`; `AntlersNode` keeps rejecting arguments and `n:antlers`; `ModifierExtension` never overrides existing filters and always passes `[]` context.
- The `statamic-latte-temp` namespace must point at `config('view.compiled')`.

## Pitfalls

- Extension constructor trap: `new $extension($engine)` for ALL extensions — see registration section.
- Modifiers registered after boot don't become filters (snapshot at `addExtension()` time).
- Temp view files accumulate in the compiled dir; `php artisan view:clear` removes them and they regenerate on next compile.
- `{yield}` inside `{cache}` caches the placeholder token — unsupported, no compile-time guard.
- The tag-bridge loader's PROTECTED regex keeps `{antlers}` bodies verbatim through source rewriting; an `{antlers ...}` opening tag containing `}` would break protection (moot while arguments are rejected).
- Forgetting a child in `getIterator()` hides it from compiler passes — n:attr unwrapping and future passes silently skip it.

## How to verify a change

- Focused: `./vendor/bin/pest tests/Feature/SlotTest.php` (or `LayoutTest`, `ModifierTest`, `NAttributeTest`, `SmartAttributesTest`, `tests/Tags/SectionTest.php`, `tests/Tags/AntlersTest.php`).
- Wiring: `tests/Feature/ServiceProviderTest.php` (extensions installed, temp namespace registered), `tests/Feature/EngineDelegationTest.php` (NormalizingEngine is the 'latte' resolver).
- Full gate: `composer test`, `composer analyse`, `composer lint` (fix with `composer format`).
- Inspect compiled PHP and temp views in the compiled dir — see the debugging skill.

## Related skills

- **tag-bridge** — TagExtension/TagNode, `(s:...)` syntax rewriting.
- **caching** — CacheNode/NocacheNode/Cache internals.
- **data-layer** — Content/Deferred/Resolver, the unwrap contract, shared FQCN-rename grep rule.
- **debugging** — compiled-output inspection workflow.
- **testing** — `$this->latte()` helper, fixtures, static-state resets.
- **template-syntax**, **orientation**, **quality-gates**.
