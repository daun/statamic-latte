# The Latte Expression Language

What you can write inside `{...}` tags: PHP expressions plus Latte sugar, minus statements.

## Contents
- Allowed PHP
- Forbidden constructs
- Latte-only sugar
- Bare (unquoted) strings — exact rules
- Filter syntax in depth
- Strings and interpolation
- Assignments and destructuring
- Errors and deprecations to know

## Allowed PHP

- All operators, incl. `??`, `?:`, `?->`, `instanceof`, `@` suppression, `clone`, string/array literals, numeric separators (`1_000`, `0o777`, hex/bin).
- Function, method, static calls: `A::b()`, `static::b()`, `$a::b()`, `A::{'b'}()`; class constants; named arguments `foo(a: $b)` (even reserved words: `bar(class: 0)`); trailing commas everywhere.
- `match` expressions (multi-condition arms, `default`).
- Arrow functions `fn($x) => expr` (types, by-ref, variadics OK). Closures `function ($a) use (&$b) { return expr; }` — the body may contain **only a single `return expr;` or nothing**; `static function` is forbidden.
- First-class callables `strlen(...)`, `$obj->m(...)`, `A::m(...)` — but not `new Foo(...)` and not as a filter; `?->m(...)` combo forbidden.
- `new` in all forms incl. PHP 8.4 parens-free dereference `new A()->foo`, dynamic classes `new $a->b()`.
- `isset($a, $b)`, `empty()`, casts — only `(array) (bool) (float) (int) (object) (string)`; long forms `(boolean)`, `(double)`, `(real)`, `(unset)` rejected.
- PHP 8.5 pipe operator `|>`.
- Comments inside tags: `/* ... */` only (`//` and `#` are syntax errors).

## Forbidden constructs

- Statements and declarations: `if/foreach/while/switch/return/throw/yield $x`, named functions, classes — use Latte tags instead.
- Language constructs: `echo`, `unset`, `include`, `require`, `exit`, `eval` ("Keyword 'X' cannot be used in Latte"), backticks.
- Variable variables `$$a`; curly string offsets `$a{'b'}`; `$GLOBALS`; `$ʟ_*` (reserved); `$this` (deprecated, blocked under StrictParsing).
- Multi-statement PHP only via `{php ...}` with `RawPhpExtension` enabled.

## Latte-only sugar

| Sugar | Meaning |
|---|---|
| `$a ? $b` | short ternary — `$a ? $b : null` |
| `$item in $items` | `in_array($item, $items, true)` — always strict; binds tighter than `\|\|` |
| `[key: value]` | `['key' => value]` — modern array keys |
| bare words | auto-quoted strings (rules below) |
| `(expr\|filter)` | filters inside expressions |
| `expr?\|filter` | nullsafe filter pipe |
| `(expand) $arr` | historic `...$arr` |

## Bare (unquoted) strings — exact rules

`{var $arr = [hello, btn--default, foo.bar]}` ≡ `['hello', 'btn--default', 'foo.bar']`. A bare word is treated as a string when it:

- contains only letters, digits, `_`, `-`, `.` (dashes fine: `a-b-c`),
- does not start with a digit, does not start/end with `-`,
- is NOT all-caps-with-underscores (`PHP_VERSION` is a constant — write global constants as `\MY_CONST`),
- is not a keyword: `and, array, clone, default, false, in, instanceof, new, null, or, return, true, xor`.

Used heavily in tag arguments: `{include sidebar}`, `n:class="active"`, `{embed 'x.latte', modifierClass: my-style}`, `hasBlock(main)`.

## Filter syntax in depth

```latte
{$s|truncate: 10, 20|trim}          {* first arg after :, more after , or : *}
{$s|truncate(a: 10, b: true)}       {* call form, named args, trailing comma OK *}
{$left . ($middle|upper) . $right}  {* in expressions: parenthesize; filter applies to the WHOLE preceding expression *}
{$a|truncate: 10, (20|round)}       {* nested filtered expressions *}
{$title?|upper|truncate:30}         {* ?| anywhere in chain; null short-circuits the rest *}
```

- Space before `|` allowed. Chain runs left to right.
- Content-aware (block) filters cannot be nullsafe.
- `|noescape`, `|nocheck`, `|json` are compiler modifiers, not real filters: position-restricted (`|json` must be last, attribute-only; `|noescape` forbidden in HTML comments).
- Explicit `|escape` is a compile error — escaping is context-driven.

## Strings and interpolation

- Single/double quotes, heredoc/nowdoc. Double-quoted interpolation: `"$a"`, `"$a->b"`, `"$a[key]"`, `"{$a['b']}"` — but **`"${b}"` is NOT supported**; binary `b""` rejected.
- Methods on literals work: `"foo$bar"[0]`, `'string'->length()` (if such method exists via extensions).

## Assignments and destructuring

Valid in value contexts (`{var}`, `{do}`, `{default}`, loop heads):

- `=`, all compound operators (`+=`, `.=`, `**=`, `??=`, `<<=`, ...), chains `$a = $b *= $c`, by-ref `$a =& $b`, `++$a`/`$a--`.
- Destructuring: `[$a, [$b]] = $x`, `list($a, , $c) = $x`; in foreach: `{foreach $arr as [$a, $b]}`. Empty array elements `[1, , 2]` and spread on the LHS are forbidden.
- Cannot assign to non-lvalues (`trim() = $x`) or take references through `?->`.

## Errors and deprecations to know

- `??->` (undefined-safe) parses but is **deprecated** — use `??` or ensure the variable exists and use `?->`. Undefined variables follow normal PHP warning semantics; only `??`/`??->` silence them.
- Invalid octal `0787`, trailing `100_`, unterminated strings/comments inside tags: compile errors.
- Unknown filter/function: compile error with "did you mean" suggestion.
- Templates must be valid UTF-8; control characters are forbidden.
