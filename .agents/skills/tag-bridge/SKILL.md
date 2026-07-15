---
name: tag-bridge
description: "The s: tag system that bridges Statamic tags into Latte templates — loader rewrites, TagNode compilation, runtime fetch/wrap, pagination, argument syntax. Use when adding/changing anything under src/Latte/Support/Tag*, src/Latte/Loaders/, src/Latte/Extensions/TagExtension.php, Nodes/TagNode.php, or when a {s:...} tag, (s:...) expression, or s() call misbehaves (wrong params, no output, fields iterated instead of entries, missing paginator)."
---

# tag-bridge: the Statamic `s:` tag system

Latte resolves tags by exact pre-registered name at compile time with a strict argument
grammar. Statamic dispatches tag methods (including wildcards like `nav:breadcrumbs` with
no declared PHP method) at runtime, with colon-laden nested param keys Latte's grammar
rejects. The bridge reconciles the two via source rewrites in the loader (the ONLY
pre-parse hook — Latte's lexer/parser classes are `final`), a custom AST node, and runtime
normalization through the data layer's `Content::wrap`.

## When to use this skill

- Making a Statamic or addon tag work (or debugging why it doesn't) in `.latte` templates.
- Extending argument syntax (`TagArguments`), the loader rewrites, or `TagNode` emission.
- Adding/removing entries in the `$unsupportedTags` blocklist.
- Pagination issues with `paginate:` params.
- Anything touching `src/Latte/Support/Tags.php`, `TagArguments.php`, `TagMethodSyntax.php`,
  `TagExpressionSyntax.php`, `src/Latte/Loaders/TagMethodLoader.php`,
  `src/Latte/Extensions/TagExtension.php`, `src/Latte/Extensions/Nodes/TagNode.php`.

## The three surfaces (all supported, all tested)

1. **Block/self-closing tags**: `{s:collection from: pages}...{/s:collection}`, `{s:link to: "x" /}`.
2. **Inline expressions**: `(s:collection:count in: pages)` — usable anywhere Latte accepts
   an expression: `{var $x = (s:...)}`, `{if (s:...) > 5}`, n:attributes.
3. **Direct function calls**: `s()` / `statamic()` as plain Latte functions with
   Latte-native argument syntax — `{s(link, to: "fanny-packs")}`,
   `{var $entries = s("collection", ["from" => "pages", "paginate" => 1])}`. This spelling
   bypasses the loader rewrites entirely (no `(s:...)` syntax involved) and is a supported
   surface of its own, covered by tests/Feature/HelperTest.php. Don't forget it exists when
   changing `Tags::fetch` — it is the raw entry point all three surfaces funnel into.

## Full compile path: `{s:collection:pages param: value}` → output

**Stage 1 — loader rewrite** (`TagMethodLoader::getContent` in
src/Latte/Loaders/TagMethodLoader.php). Wired by `ServiceProvider::installLoader` as
`new TagMethodLoader(new LaravelViewLoader($view))`. `rewrite()` splits the source on the
`PROTECTED` regex (`{* comments *}` and `{antlers}...{/antlers}` islands) with
`PREG_SPLIT_DELIM_CAPTURE`; only even-indexed (unprotected) parts are rewritten. Order is
load-bearing: `TagMethodSyntax::rewrite(TagExpressionSyntax::rewrite($part))` — the
**expression rewrite runs FIRST**, because it must consume `(s:...)` groups before the
method-syntax regex (which matches on braces and tag text) can touch them.

**Stage 2a — expression lowering** (`TagExpressionSyntax::rewrite` in
src/Latte/Support/TagExpressionSyntax.php). Scans for `(`, finds the matching `)` with the
nesting/quote-aware `matchParen()`, and if the inner text matches `#^s:[A-Za-z_]#`, parses
it via `TagArguments::parse` and prints it back as `(s('collection:count', ['in' => 'pages']))`
— a call to the registered `s()` function. The rewrite is a deliberate **catch-all**:
unregistered tag names are still lowered; existence is checked at runtime by
`Statamic::tag()` so cached templates never bake in a tag-registry snapshot. A bare
top-level pipe (`in: $x|lower`) throws a CompileException because Latte's argument grammar
would silently drop the filter; the parenthesized form `in: ($x|lower)` works.

**Stage 2b — method-syntax lowering** (`TagMethodSyntax::rewrite` in
src/Latte/Support/TagMethodSyntax.php). `{s:collection:pages sort: title}` becomes
`{s:collection __sl_tag: "collection:pages", sort: title}` — the full method name is
smuggled through as an internal argument keyed `__sl_tag` (const
`TagMethodSyntax::TAG_ARGUMENT`). Closing tags drop the method:
`{/s:collection:pages}` → `{/s:collection}`. Needed because only base tag handles can be
pre-registered with Latte; methods (incl. wildcards) resolve at runtime.

**Stage 3 — registration & parsing** (`TagExtension` in
src/Latte/Extensions/TagExtension.php). Constructor stores a live reference to
`app('statamic.tags')` (Statamic registrations mutate that same Collection in place);
Latte re-reads `getTags()` at every template compile. `getTags()` maps every handle to
`s:`-prefixed names, all pointing at `[TagNode::class, 'create']`. `getFunctions()`
registers `statamic` and `s`, both as `[Tags::class, 'fetch']`. Consequence: the block
form supports any tag registered by the time the template COMPILES — the real caveat is
the compile cache: an already-compiled template won't pick up newly registered tags until
recompiled (`php artisan view:clear`), whereas `(s:...)`/`s()` in cached templates resolve
at runtime.

**Stage 4 — node creation** (`TagNode::create` in src/Latte/Extensions/Nodes/TagNode.php).
Checks `$unsupportedTags` (CompileException with pointer to the native equivalent), parses
arguments via `TagNode::parseArguments` → `TagArguments::parseParams`, then drains the tag
parser stream so Latte sees the args as consumed (skip this and Latte reports leftover
tokens as a syntax error). Yields for the body; `selfClosing = ! $endTag || $endTag === $tag`.

**Stage 5 — emission** (`TagNode::print`). `splitArguments()` extracts three
bridge-consumed params that must NEVER be forwarded to the Statamic tag: `__sl_tag`
(must be a literal StringNode), `as` (only when a literal StringNode — see below), and
`content` (any expression). Emits
`$ʟ_result = \Daun\StatamicLatte\Latte\Support\Tags::fetch(%dump, %node);` (or
`fetchWithContent(%dump, (string) (%node), %node)` when `content:` is given), followed by
either `printAliased()` or the generic body dispatch.

**Stage 6 — runtime** (`Tags::fetch`/`fetchWithContent` → `run` → `fetchTag` in
src/Latte/Support/Tags.php). `run()` merges positional non-list array args into params (so
`s("collection", [...])` works), builds `Statamic::tag($name)->params($params)`, applies
`->withContent($content)` if set. `fetchTag()` translates `BadMethodCallException` into a
friendlier `{s:users:count}: 'count' is not a valid method...`, handles pagination (below),
and otherwise returns `Content::wrap($result)` so tag output has the same shapes as view
data (see data-layer skill).

## Argument syntax rules (TagArguments)

src/Latte/Support/TagArguments.php is shared by the block form and the expression form —
one change covers both (invariant). Mechanism: `escapeNestedKeys()` scans char-by-char
(quote-aware) and masks colons that CONTINUE a key with the placeholder `__sl_colon__`
(`TagArguments::COLON_PLACEHOLDER` — a private const; when asserting on
`escapeNestedKeys()` output, match the literal `__sl_colon__`), so Latte's own
`TagLexer`/`TagParser` accept the text;
`parseParams()` then restores colons on parsed keys — both `IdentifierNode` keys (from
`key: value`) and `StringNode` keys (from `key => value`).

The decision rule (`colonContinuesKey()`): a colon followed by a non-word char is always
the key/value separator; a colon followed by a bareword is masked only if that bareword is
itself followed by another `:` (deeper nesting) or `=` (start of `=>`). All of these work:

- `title:contains: Layout` and `status:is => draft`
- `title:contains:"Layout"` and `title:contains:Layout` (last colon = separator)
- `title:contains: $var`, `title:contains:$var`, `title:contains:$a.$b` (values are
  ordinary Latte expressions)

**Do not "simplify" this scanner.** Each rule is a separate regression fix: commit
`7d77b16` (nested param keys), `3aed979` (end key at first non-word char), `be5faed`
(restore keys for fat-arrow syntax). Any change must pass the data-provider test
"treats the last colon of a bareword chain as the key/value separator" in
tests/Unit/TagExpressionSyntaxTest.php AND the `describe('params')` matrix in
tests/Feature/TagTest.php.

## Output capture and scope semantics

**Pair vs self-closing.** Latte 3+ cannot register a tag name as both single and paired
(nette/latte#382 — see the commented-out "renders s:link single tag" test inside
`describe('scalar tags')` in tests/Feature/TagTest.php).
`{s:link to: "x"}` without a closing tag is NOT a supported single tag; use `{s:link ... /}`
or a full pair.

**Body dispatch (no `as`).** The compiled code computes
`$ʟ_iterable = is_iterable($ʟ_result) && ! $ʟ_result instanceof \Daun\StatamicLatte\Data\Content`.
The Content exclusion exists because `Content` implements `IteratorAggregate`: without it,
a single wrapped entry would enter the foreach branch and iterate its FIELDS instead of
rendering once with `$value` = the entry. If iterable, the body runs inside a genuine Latte
`ForeachNode` (`TagNode::printForeach`) as `foreach ($ʟ_result as $value)` — which is why
`$iterator`, `{sep}`, `{first}`, `{last}` work inside tag pairs. Otherwise, if the result
is not null/''/false, `$value = $ʟ_result` and the body renders once. Empty-body or
self-closing non-iterable tags fall back to `Tags::stringifyResult($ʟ_result)` (scalars and
Stringables print; booleans print as ''; wrappers are drilled via
`Resolver::actual(Content::unwrap(...))`; everything else prints '' instead of fataling).

**Scope contract:** unlike Antlers, loop-item keys are NOT hoisted into scope. The item is
exposed only as `$value` (`{$value->title}`). This is deliberate — Latte has real lexical
scope; hoisting would shadow template variables unpredictably.

**`as:` capture.** `{s:collection as: entries, ...}` binds the RAW result (including a live
paginator) to `$entries` and renders the body exactly once — no automatic foreach.
`printAliased()` backs up `get_defined_vars()`, assigns, and restores/unsets in a `finally`,
so the alias is strictly body-scoped. Only a LITERAL string value triggers capture;
`as: $dynamic` is forwarded to the Statamic tag as a regular param (intentional — some tags
take their own `as`).

**Parenthesized assignment.** `{var $x = (s:collection ... paginate: 1)}` — the expression
form; the raw normalized result (paginator included) lands in `$x`.

**`content:` param.** Supplies the tag-pair body as a pre-rendered string for
content-transforming tags (widont, obfuscate, mjml): `{capture $text}...{/capture}
{s:widont content: $text /}`. Cast `(string)` at the call site, routed through
`FluentTag::withContent()`, never forwarded as a param.

## Pagination

Statamic flattens paginated results to a plain array but stashes the real Laravel paginator
in Blink under `'tag-paginator'` first (verified: `Blink::put('tag-paginator', $paginator)`
in vendor `Statamic\Tags\Concerns\GetsQueryResults::paginatedResults`). `Tags::fetchTag`:

1. snapshots the existing Blink value before `$tag->fetch()` without removing it — addon
   tags and Statamic's static-cache middleware may need that shared paginator state;
2. runs the tag;
3. if the Blink value is a new `AbstractPaginator` instance, returns `normalizePaginator()`
   while leaving the slot intact — items are `Content::wrap`ped in place and paginator API
   (`total()`, `currentPage()`, `links()`) remains available.

The identity comparison prevents a previous tag's paginator from being mistaken for the
current non-paginated tag's result without destroying request-scoped Blink state.

Built test-first (commit `9a27c32` + tests `865a613`, `241d32f`, `161ec40`) across ALL
capture styles: plain pair, `as:` param, `{var $x = (s:...)}` subexpression, and
`s("collection", [...])` function call (HelperTest). If you bypass `Tags::fetch` when
invoking tags, pagination silently degrades to a keyed array.

## The `$unsupportedTags` blocklist

`TagNode::$unsupportedTags` currently blocks: `cache`, `foreach`, `partial`, `switch`,
`translate`, `trans_choice`, `yield`, `section`, `scope`, `loop`, `increment`, `dump` — each with a message pointing at the native Latte equivalent (except `scope`,
which is simply "Not supported in Latte" — blocking without an equivalent is allowed when
the construct has no Latte counterpart), thrown as a CompileException in `TagNode::create`.

Entries were added inside PR #14 (`feat/compat`, commits `ddc5569`, `4a17d4a`) when the
tag-compat suite proved a native Latte construct strictly better. **New candidates must
follow the same evidence path**: write a compat test in tests/Tags/ first, demonstrate the
native equivalent, then add the blocklist entry + a test in
tests/Tags/UnsupportedTagsTest.php asserting the CompileException message.

## Invariants — never break these

- **`$ʟ_` prefix** (U+029F LATIN LETTER SMALL CAPITAL L, not plain `l`) on EVERY local
  emitted in compiled PHP (`$ʟ_result`, `$ʟ_iterable`, `$ʟ_body`, `$ʟ_as_<id>`). It is
  Latte's reserved compiler-internal namespace; a plain `$result` could be clobbered by a
  user template variable. Uniquify nestable temporaries with `$context->generateId()`.
- **Baked FQCN strings**: compiled code references `\Daun\StatamicLatte\Latte\Support\Tags`
  and `\Daun\StatamicLatte\Data\Content` as STRING literals inside `TagNode::print` — a
  class rename must update them by hand. After any rename run
  `grep -rln '\\Daun' src --include='*.php'` to find all baked-string sites (matches the
  leading `\Daun` of FQCN literals) — six files emit them, not just
  TagNode: TagNode, CacheNode, SectionNode, YieldNode, AntlersNode,
  AttributeNormalizationExtension.
- `as`, `content`, `__sl_tag` are bridge-consumed and never forwarded to the tag.
- All non-paginator output goes through `Content::wrap`; paginator objects survive intact
  with items wrapped.
- Preserve Blink's `'tag-paginator'` slot; identify a paginator created by the current tag
  through before/after object identity.
- Both argument entry points (`TagNode::parseArguments`, `TagExpressionSyntax::rewriteCall`)
  share `TagArguments`.
- Loader rewrites are pure string→string, skip protected regions, and
  `getReferredName`/`getUniqueId` are delegated untouched (template identity/caching).
- Expression rewrite stays a catch-all with runtime resolution — no compile-time registry
  snapshot in cached templates.
- Iteration goes through Latte's real `ForeachNode`, never a hand-rolled foreach.

## Pitfalls

- `(s:<identifier> ...)` is reserved syntax EVERYWHERE outside protected regions — literal
  prose `(s:foo bar)` gets rewritten. `(s::FOO)` static-call lookalikes are safe.
- `TagExpressionSyntax::rewriteCall` hard-codes `s(` in its output — renaming the canonical
  function means editing that string too.
- Writing `__sl_tag: "x"` manually in a template overrides the method name.
- Booleans never print (Antlers parity: a bool controls the pair, doesn't print `1`).
- After changing any loader/syntax class, clear compiled views — `vendor/bin/testbench
  view:clear` in this repo (there is no `artisan` binary), `php artisan view:clear` in a
  consuming app — loader output feeds the compile cache. Tests are unaffected: each
  `$this->latte()` call writes a uniquely-named temp template, so it always recompiles.
- A tag registered at runtime works in ALL three surfaces for templates compiled
  afterwards; but a template already compiled and cached before the registration keeps
  failing as a `{s:...}` block tag until recompiled (`php artisan view:clear`), while its
  `(s:...)`/`s()` spellings still resolve the new tag at runtime.
- Parenthesized filters in params (`in: ($x|lower)`) work only because Latte prints them as
  recompilable source — the test "compiles a parenthesised filter on a param value" in
  tests/Unit/TagExpressionSyntaxTest.php is the canary for Latte upgrades.

## Recipe: make a misbehaving Statamic tag work

1. **Reproduce** in a new/existing tests/Tags/<Tag>Test.php using
   `$this->latte('...template...')` (tests/Concerns/InteractsWithLatteViews.php — renders
   through the FULL pipeline including loader rewrites; pass `squish: false` to preserve
   whitespace). Copy the shape of tests/Tags/LinkTest.php or CollectionTest.php.
2. **Trace which stage breaks**:
   - *Rewrite*: call `TagMethodSyntax::rewrite($src)` / `TagExpressionSyntax::rewrite($src)`
     directly — they are pure string functions, no container needed.
   - *Parse*: "Unexpected tag" at compile time ⇒ tag wasn't in `app('statamic.tags')` when
     the template was COMPILED — check for a stale cached compile (`view:clear`) or
     registration that happens after the first render; probe with the `(s:...)` inline
     form. Argument errors ⇒ dump
     `TagArguments::escapeNestedKeys('...')` to see the masked text.
   - *Runtime fetch*: dump inside `Tags::fetchTag` — what did `$tag->fetch()` return, what's
     in `Blink::get('tag-paginator')`? A "not a valid method" error means smuggling worked
     but Statamic rejected the method.
   - *Result wrap*: fields iterated instead of entries ⇒ the Content guard; empty output ⇒
     `stringifyResult` fallback rules. Inspect compiled PHP in
     vendor/orchestra/testbench-core/laravel/storage/framework/views/ (this repo runs
     under Testbench; in a consuming app it's storage/framework/views) — files named
     `-latte--<hash>.php`; every emission has a `%line` comment mapping back to the
     template line.
3. **Fix** at the failing stage; keep the invariants above.
4. **Classify** the outcome in the test file comment, matching existing conventions in
   tests/Tags/: `OK`, `INCOMPAT`, `N/A`, `BEHAVIOUR SHIFT`, `FIXTURE` (see
   tests/Tags/ProtectTest.php, ResponseTagsTest.php for `N/A`/`INCOMPAT-candidate` examples).
   If the verdict is "native Latte is strictly better", follow the blocklist path above.

## Recipe: extend argument syntax

1. Change lives ONLY in `TagArguments::escapeNestedKeys` + `colonContinuesKey`
   (src/Latte/Support/TagArguments.php) — both forms share it.
2. Add matrix cases to the data-provider test in tests/Unit/TagExpressionSyntaxTest.php and
   the `describe('params')` block in tests/Feature/TagTest.php.
3. Mirror pure-rewrite assertions in tests/Unit/TagMethodSyntaxTest.php if the block-tag
   text shape changes.
4. Run the FULL tag suite (all three commits' regressions must stay green).

## How to verify a change

```sh
./vendor/bin/pest tests/Feature/TagTest.php tests/Feature/HelperTest.php tests/Unit
./vendor/bin/pest tests/Tags/<RelevantTag>Test.php
composer test        # full suite (378 tests, ~19s) before merging
composer lint        # pint --test
composer analyse     # phpstan level 5
```

Feature tests exercise the full pipeline; a failing feature test with passing unit tests
points at TagNode emission or runtime `Tags::` behavior, not the rewrites.

## Related skills

- **data-layer** — Content/Deferred/Resolver, what `Content::wrap` does to tag results.
- **extensions-and-nodes** — writing new Latte nodes, `$context->format()` placeholders.
- **template-syntax** — user-facing syntax contract (README Tags section).
- **testing** — `$this->latte()` helper, fixtures (pages collection: "Testable",
  "Testable With Layout", draft "Testable Draft"), tests/Tags conventions.
- **caching** — compiled-template cache, why loader output feeds the cache.
- **debugging** — reading compiled PHP, `%line` markers.
- **orientation**, **quality-gates** — repo layout and CI (PHP 8.3–8.5 × Laravel 12/13 × Statamic 6).
