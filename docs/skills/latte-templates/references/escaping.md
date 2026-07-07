# Context-Aware Escaping and HTML Handling

How Latte prints values safely in every context, what it does to your HTML, and the whitespace rules. Reference for Latte 3.x.

## Contents
- The escaping model
- JavaScript context (`<script>`, on* attributes)
- CSS and comments
- Raw-text elements: script/style gotcha
- Printing into attributes (type-driven semantics)
- URL sanitization
- HTML parsing strictness
- Dynamic tags and generated attributes
- contentType and non-HTML templates
- Whitespace handling

## The escaping model

Latte parses the HTML, so it knows the context of every tag and layers escaping accordingly (e.g. a value in `onload` is JS-escaped, then attribute-escaped). With `$text = "Rock'n'Roll"`:

```latte
<span>{$text}</span>            → <span>Rock'n'Roll</span>
<span title='{$text}'></span>   → <span title='Rock&apos;n&apos;Roll'></span>
<span title={$text}></span>     → <span title="Rock&apos;n&apos;Roll"></span>   (quotes added)
<script>let a = {$text};</script> → let a = "Rock'n'Roll";  (JSON-encoded)
<!-- {$text} -->                → <!-- Rock'n'Roll -->
```

You cannot forget to escape and you cannot double-escape. Opt out only with `|noescape` (trusted HTML) or by passing `Latte\Runtime\Html` objects from PHP. `{capture}` also produces `Html` — re-printing it doesn't double-escape, and a capture made inside an HTML comment keeps comment context.

## JavaScript context

- Values print as JSON — strings get quotes, arrays/objects become literals, `</` becomes `<\/`:

```latte
<script>
    let movie = {$movie};              {* any type *}
    alert('Hi ' + {$name});            {* concatenate, don't interpolate *}
</script>
<p onclick="alert({$movie})">          {* JS-then-attribute escaping *}
```

- **Never put a print tag inside JS quotes**: `"{$var}"` in a script is the compile error "Do not place print statement inside quotes".
- `<script type=...>` matters: `application/json`, `ld+json`, `importmap`, `module`, empty, and `text/javascript` variants → JS/JSON escaping; `text/html` → HTML escaping (with `</script` neutralized); unknown types → raw text with only `</script` escaped; `{include}` of an HTML block into an unknown-type script throws at runtime.

## CSS and comments

- `<style>` contents get CSS escaping (`<` → `\<`, quotes in `url("...")` handled).
- HTML comments `<!-- {$x} -->`: sequences like `--` and `>` are defused (`--` → `- -`); `|noescape` is a compile error inside comments.

## Raw-text elements: script/style gotcha

The content of `<script>` and `<style>` is raw text to the HTML parser:

- Latte tags `{...}` still work, but **HTML elements and n:attributes inside are NOT processed**. `<div n:foreach="...">` inside `<script type="text/html">` stays literal text.
- Escape hatch: `{contentType html}` immediately after the opening `<script ...>` tag re-enables HTML parsing inside it.
- `{` followed by whitespace/quote/brace is literal, so most JS/CSS needs no changes; for `{identifier` collisions use `<script n:syntax="off">`.

## Printing into attributes (type-driven semantics)

When an attribute's whole value is one tag (`title={$x}` or via `n:attr`):

| Attribute kind | Behavior |
|---|---|
| regular (`title`) | `true` → bare `title`; `false`/`null`/`[]` → attribute omitted; scalars escaped; arrays → warning + omitted |
| boolean (`checked`, `disabled`, ...) | truthy → present, falsy (`0`, `''`, `[]`, `false`, `null`) → omitted |
| `style` | array → `color: red; font-size: 16px` (k => v) or `;`-joined list |
| `class`, `aria-*` | array → space-joined; string keys with truthy values keep the key: `{['a' => true, 'b' => false]}` → `a` |
| `data-*` | scalars stringified (`true` → `"true"`), null omitted, arrays → JSON (auto single-quoted) |
| `on*` | quoted `onclick="{$x}"` JS-encodes; full-value unquoted prints plain string |

- `|json` *(unreleased — master post-3.1.4)*: as the LAST modifier of a dynamic attribute, forces JSON with smart quoting and overrides class/aria/data special handling: `<div class={$list|json}>` → `class='["a","b"]'`.
- In XML mode there are no boolean/special attributes: `true`/`false`/`null`/arrays all drop the attribute.
- Partial values compose: `href="{$url}#anchor"`, `class="static {$dynamic}"`.

## URL sanitization

Applies when the whole value of `href`, `src`, `action`, `formaction` (or `data` on `<object>`) is a single print tag:

- Allowed: `http:`, `https:`, `ftp:`, `mailto:`, `tel:`, `sms:`, relative URLs, `//host`.
- Anything else (`javascript:`, data-HTML) → printed as `""`.
- `|nocheck` disables the check for a value; `|checkUrl` applies the same check to non-monitored attributes (`data-href={$link|checkUrl}`).
- NOT applied to partial values (`href="{$u}#frag"`) or to other attributes.

## HTML parsing strictness

- `<script>` and `<style>` must be explicitly closed in HTML mode — compile error otherwise.
- Elements carrying n:attributes (and dynamic tag names) must be correctly paired; other mismatched closers are tolerated unless `Feature::StrictParsing` is on (then everything must pair).
- Self-closing non-void elements are expanded: `<div />` → `<div></div>`.
- An element suppressed by `n:if="false"` tolerates unbalanced HTML inside.
- `n:ifcontent` on a void element (`<br>`) is a compile error.

## Dynamic tags and generated attributes

```latte
<h{$level} class="main">...</h{$level}>     {* closer must repeat the same expression *}
<{$ns}:{$tag}>...</{$ns}:{$tag}>
<span {if $a}title={$x}{else}data-x=1{/if}>  {* tags can generate whole attributes *}
<span title=c{$x}d>                          {* mixed literal + tag in unquoted value *}
```

Restrictions: a `{if}` may not span an attribute-name/`=` boundary; quoted strings/spaces can't ride inside a generated unquoted value; n:attributes can't be generated by tags; resulting tag names are validated. `n:tag` cannot switch to/from void elements or to `script`/`style`.

## contentType and non-HTML templates

`{contentType}` in the template header switches the whole template's escaping rules: `html` (default), `xml`, `javascript`, `css`, `calendar` (iCal), `text` (escaping off — beware). A full MIME type also sends the HTTP header: `{contentType application/xml}`. In text mode nothing HTML-ish is parsed at all.

## Whitespace handling

- A control tag (or `{* comment *}`) alone on a line: the entire line vanishes from output (indentation + newline).
- Printing tags (`{$var}`, `{=...}`, `{_...}`) keep surrounding whitespace.
- A tag not alone on a line: preceding whitespace belongs "inside" it — `{if false}` suppresses its own line's leading indentation.
- Inline comments keep both neighbor spaces: `a {* c *} b` → `a  b` (two spaces).
- `{spaceless}` / `n:spaceless` / `|spaceless` collapse inter-tag whitespace (see [tags.md](tags.md)).
- Opt-in `Feature::Dedent` strips one structural indent level inside paired tags; mixed tabs/spaces there throw "Inconsistent indentation".
