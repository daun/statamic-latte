# Latte Filters and Functions

All built-in filters and expression functions, with signatures. Filters assume UTF-8.

## Contents
- Filter syntax recap
- String filters
- Casing filters
- Number filters
- Date filters
- Array/iterable filters
- HTML attribute filters
- Escaping, URL, and security modifiers
- Aliases and requirements
- Functions (usable in any expression)

## Filter syntax recap

```latte
{$title|upper}                      {* apply *}
{$title|lower|capitalize}           {* chain, left to right *}
{$text|truncate: 20, ''}            {* args: colon first, then commas (colons also OK) *}
{$date|localDate: format: yM}       {* named args *}
{$title?|upper}                     {* nullsafe pipe: whole chain skipped when value is null *}
{var $x = ($title|upper)}           {* inside expressions: parentheses required *}
{block|spaceless} ... {/block}      {* filters on blocks / capture / include output *}
```

Function-call form also works: `|truncate(a: 10)`. Unknown filters are compile errors with a "did you mean" hint.

## String filters

| Filter | Signature / behavior |
|---|---|
| `truncate` | `(length, append = '…')` — shorten preserving whole words: `{$s\|truncate:10}` |
| `substr` | `(start, length = null)` — prefer `slice` |
| `slice` | `(start, length = null, preserveKeys = false)` — strings, arrays, iterators; negative offsets as in PHP |
| `limit` | `(length)` — first N items/chars, keys preserved |
| `trim` | `(charlist = whitespace + nbsp)` — `{$s\|trim: '.'}` |
| `stripHtml` | removes tags AND decodes entities → plain text. Never re-output with `\|noescape` (XSS) |
| `stripTags` | removes tags but KEEPS entities; output remains HTML (`strip_tags()`) |
| `breakLines` | inserts `<br>` before newlines, escapes the rest |
| `spaceless` | collapse whitespace (also tag/n:attr). Deprecated alias `strip` |
| `indent` | `(level = 1, chars = "\t")` — indents non-blank lines |
| `padLeft` / `padRight` | `(length, pad = ' ')` |
| `repeat` | `(count)` |
| `replace` | `(search, replace = '')` — strings or arrays: `{$s\|replace: [h => l]}` |
| `replaceRE` | `(pattern, replacement = '')` — regex (alias `replaceRe`) |
| `reverse` | reverses UTF-8 string or array |
| `length` | UTF-8 chars / count() / iterator_count() |
| `webalize` | URL slug (`our-10th-product`); requires nette/utils |
| `translate` | `(...args)` — requires TranslatorExtension |

## Casing filters (require ext-mbstring)

`capitalize` (Each Word Upper), `firstUpper`, `firstLower`, `lower`, `upper`.

## Number filters

| Filter | Signature / behavior |
|---|---|
| `number` | `(decimals = 0, decPoint = '.', thousandsSep = ',')` — `{1234.2\|number:2}` → `1,234.20`. Alternative signature `(string $icuPattern)` needs `setLocale()`: `{1234.5\|number: '#,##0.00'}` |
| `round` / `floor` / `ceil` | `(precision = 0)` |
| `clamp` | `(min, max)` |
| `bytes` | `(precision = 2)` — `1.25 GB`; locale-aware |

## Date filters

| Filter | Signature / behavior |
|---|---|
| `date` | `(format = 'j. n. Y')` — PHP date() mask; accepts timestamp, string, DateTimeInterface, DateInterval; null → null |
| `localDate` | `(format?, date?, time?)` — locale formatting, requires `Engine::setLocale()`. Presets `date: full\|long\|medium\|short\|relative-short`; ICU skeletons `format: 'yMMMMd'` (letter order irrelevant — locale decides) |

## Array/iterable filters

| Filter | Signature / behavior |
|---|---|
| `batch` | `(length, rest = null)` — chunk into rows: `{foreach ($items\|batch: 3, 'n/a') as $row}` |
| `group` | `(by)` — key, or closure; preserves keys and order: `{foreach ($items\|group: categoryId) as $catId => $items}` |
| `filter` | `(predicate)` — keep matching items, keys preserved |
| `sort` | `(comparison?, by?, byKey?)` — keys preserved, locale-aware for strings. `($items\|sort: by: 'name')`, `($names\|sort: byKey: true)`, custom `($a\|sort: fn($x, $y) => $y <=> $x)`. `by` and `byKey` cannot combine |
| `column` | `(columnKey, indexKey = null)` — extract column from 2D array / object list |
| `implode` / `join` | `(glue = '')` |
| `commas` | `(lastGlue = null)` — `{$items\|commas: ' and '}` → `a, b and c` |
| `explode` / `split` | `(separator = '')` — empty separator splits to characters |
| `first` / `last` / `random` | element of array or char of string |
| `reverse`, `slice`, `limit`, `length` | also work on arrays/iterators (above) |

## HTML attribute filters

- `toggle` — attribute presence from a boolean; **only inside HTML attributes**: `<div uk-grid={$isGrid|toggle}>`.
- `json` — attribute-only modifier, must be **last** in the chain of a dynamic HTML attribute: `<meta content={$arr|json}>` → `content='{"a":1}'` (smart quoting). Overrides the special class/aria/data array handling. Anywhere else (text, `<script>`, mid-chain) it's an unknown-filter error.
- `dataStream` — `(mimetype = auto)` — converts content to a base64 `data:` URI; requires ext-fileinfo.

## Escaping, URL, and security modifiers

- `noescape` — disables context escaping. XSS risk; trusted HTML only.
- `checkUrl` — force URL sanitization on attributes not auto-checked: `<a data-href={$link|checkUrl}>`. Allows http/https/ftp/mailto/tel/sms + relative; unsafe → `''`.
- `nocheck` — opt OUT of the automatic URL check on `href`/`src`/`action`/`formaction`.
- `escapeUrl` — rawurlencode for embedding a value inside a URL: `href="/search?q={$q|escapeUrl}"`.
- `query` — build a query string from array/scalar; null values omitted: `href="?{[name: $n, age: 43]|query}"`.
- Internal, never write manually (auto-applied): `escapeHtml`, `escapeHtmlComment`, `escapeXml`, `escapeJs`, `escapeCss`, `escapeICal`. Explicit `|escape` is a compile error.

## Aliases and requirements

- Aliases: `join`=`implode`, `split`=`explode`, `breaklines`=`breakLines`, `striphtml`=`stripHtml`, `striptags`=`stripTags`, `datastream`=`dataStream`, `replaceRe`=`replaceRE`, `strip` (deprecated) = `spaceless`.
- Requirements: mbstring (casing), intl + `setLocale()` (localDate, ICU number, locale-aware sort/bytes), nette/utils (webalize), fileinfo (dataStream), iconv (reverse, substr).

## Functions (usable in any expression)

Complete list — called like PHP functions: `{if odd($num)}`, `{=first([1,2,3])}`.

| Function | Behavior |
|---|---|
| `clamp(value, min, max)` | clamp to range |
| `divisibleBy(value, by)` | bool |
| `even(int)` / `odd(int)` | bool |
| `first(iterable\|string)` / `last(...)` | first/last element or char |
| `group(iterable, by)` | same as the group filter |
| `slice(value, start, length = null, preserveKeys = false)` | same as the slice filter |
| `hasBlock(name)` | block exists: `{if hasBlock(header)}` (bare name OK) |
| `hasTemplate(name)` | template file exists: `{if hasTemplate('foo.latte')}` |

Ordinary PHP functions are also callable in expressions (unless restricted by sandbox). Custom filters/functions are registered from PHP — see [php-api.md](php-api.md).
