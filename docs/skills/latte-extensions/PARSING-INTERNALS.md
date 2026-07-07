# Parsing internals

## Compilation flow

```
Engine::compile():
  (1) $src  = $loader->getContent($name)   // source string  — Loader
  (2) $node = $this->parse($src)           // lexer→parser→AST — getTags + beforeCompile
  (3) $this->applyPasses($node)            // AST transforms   — getPasses
  (4) $code = $this->generate($node)       // AST→PHP
```

## What the lexer/parser actually do

- `TemplateLexer` tokenizes source. Tag names match
  `[a-z]\w*+(?:[.:-]\w+)*+` — so dotted/coloned names like `foo:bar.baz` become a
  **single** `Latte_Name` token. You cannot split them later; whatever name the
  lexer produced is what gets looked up.
- `TemplateParser::parseLatteStatement()` reads the name token, then calls
  `getTagParser($name)`. Unknown name = hard failure.
- Tag args are collected as the PHP-kind tokens after the name; they populate
  `$tag->parser` (a `TagParser`), whose `->text` is the concatenated token text.

## Impossibility of custom lexing and parsing

All three are `final`, so **no subclassing**:

- `Latte\Compiler\TemplateLexer`
- `Latte\Compiler\TemplateParser`
- `Latte\Compiler\TagLexer`

And the seams you'd need are `private`:

- `TemplateParser::$tagParsers`, `$lexer`, `$stream` — private. Lookup is
  `isset($this->tagParsers[$name])` on a typed `array`, so you can't even smuggle
  in an `ArrayAccess` object that answers dynamically.
- `Engine::parse()` does `new Compiler\TemplateParser` literally — **no hook** to
  provide your own parser or lexer.
- `Engine`'s `$syntax`, `$contentType`, `$extensions`, `$policy` are private
  (only some have getters), so even reimplementing `parse()` in an `Engine`
  subclass needs reflection and is fragile across versions.

**Therefore these are off the table:** custom lexers, token-stream rewriting.

## Dynamic tag names

If tag names are open-ended (computed at runtime, catch-all wildcard dispatch, user
handles, etc.), it must be solved at steps (1) or (2) of the compilation flow.
Passes (step 3) can never rescue it.

### The only viable answer: a Loader decorator (source preprocessing)

The `Loader` is Latte's single pre-parse hook. Rewrite the source so dynamic
names become a **registered base tag** plus the original name smuggled as an
internal argument. The split is **syntactic**, so it covers every name —
declared or dynamic.

```php
// Rewrite. No IO here.
final class TagMethodLoader implements \Latte\Loader {
    public function __construct(private \Latte\Loader $inner) {}
    public function getContent(string $name): string {
        return TagMethodSyntax::rewrite($this->inner->getContent($name));
    }
    public function isExpired(string $name, int $time): bool { return $this->inner->isExpired($name, $time); }
    public function getReferredName(string $n, string $r): string { return $this->inner->getReferredName($n, $r); }
    public function getUniqueId(string $name): string { return $this->inner->getUniqueId($name); }
}

// Compose. Inner loader stays pure IO.
$engine->setLoader(new TagMethodLoader($engine->getLoader()));
```

### Why a decorator (not editing the IO loader)

- Single responsibility: IO loader stays pure; transformation is one named class.
- Composable with **any** loader.
- The transform is a pure function ⇒ unit-test it without the filesystem.

### Caveats of source rewriting

- Regex over raw source is blind to `{* comments *}` and raw `<script>`/`<style>`.
  Keep patterns specific (require your prefix + the dynamic segment).
- It runs on every (cache-missed) compile; keep it cheap.

## Fast probing recipes

```php
$engine->parse($src);                                   // parse only — test compile-time errors
$engine->setLoader(new \Latte\Loaders\StringLoader(['t' => $src])); // in-memory full compile
echo $engine->compile('t');                             // inspect generated PHP
```
