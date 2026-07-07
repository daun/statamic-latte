---
name: latte-extensions
description: Extend the Latte template engine. Use when asked to create custom tags, nodes, compiler passes, preprocessing, extensions.
---

# Extending Latte

Read this page first, then open the reference file for the subsystem you're touching.

## Decision cheatsheet

| You need… | Use | File |
| --- | --- | --- |
| A new custom `{tag}` / `n:attr` with a **fixed name** | `Extension::getTags()` + a Node | [EXTENSIONS.md](EXTENSIONS.md) |
| To transform/lint/validate the **node tree** | `Extension::getPasses()` | [EXTENSIONS.md](EXTENSIONS.md) |
| A `|filter` or `func()` in expressions | `getFilters()` / `getFunctions()` | [EXTENSIONS.md](EXTENSIONS.md) |
| Runtime services in templates | `getProviders()` (`$this->global->x`) | [EXTENSIONS.md](EXTENSIONS.md) |
| **Arbitrary / dynamic / wildcard tag names** | A **Loader decorator** (source rewrite) | [PARSING-INTERNALS.md](PARSING-INTERNALS.md) |
| To hook the lexer/parser/tokens directly | **You can't** — see subsystem details | [PARSING-INTERNALS.md](PARSING-INTERNALS.md) |

Test parse-time behavior fast with the public API — no files needed:
`$engine->parse('{greet}x{/greet}')` or
`$engine->setLoader(new \Latte\Loaders\StringLoader(['t' => $src]))`.

## Gotchas (the ones that cost hours)

- Validate bad usage in `create()` (parse time) so it fails at compile, not render.
- Latte's arg grammar is PHP-ish and rejects foreign syntax. See the
  mask→parse→restore→drain trick in [EXTENSIONS.md](EXTENSIONS.md).
- A proxy/Loader-decorator node renders the tag-pair **body itself** (as Latte) —
  it never hands the raw body string to the wrapped engine's parser. So any
  Statamic tag that Antlers-parses its own body via `$this->parse(...)` (e.g.
  `glide:batch`, `form:fields`, `form:set`) won't receive it and breaks through
  the `s:` proxy. Workaround: capture the tag's data and loop it in Latte
  (`{s:form:create as: form}` → `{foreach $form->fields …}`).
- When forwarding a tag result to the body, mind the result type: skip the body
  for `null`/`''`/`false` (Antlers parity), never echo bare booleans, and only
  print scalars/`Stringable` on a self-closing/empty body (non-printable objects
  print nothing rather than fataling).
