# Changelog

## [Unreleased]

### Performance

- Defer augmentation of non-empty relationship fields (entries, terms, assets,
  users) until a template first uses them. Previously every relationship field
  in the cascade ran its query and augmentation at render start whether or not
  the template referenced it. Template-facing behavior is unchanged, including
  `{if $related}` truthiness and `{foreach}`/`{$related|length}`.

### Changed (internal)

- Consolidated the data layer: `Normalizer` is **deprecated** in favor of the
  static methods `Content::wrap()`, `Content::wrapAll()` and `Content::unwrap()`.
  `Normalizer` remains as a delegating shim (to be removed in 3.0) so already
  compiled templates keep working across the upgrade.
- The `Content->unwrap()` **instance** escape hatch is renamed to
  `Content->source()`; `Content::unwrap()` is now a static boundary helper.
- `Resolver::actual()` now delegates wrapper peeling to Statamic core's
  `Statamic\View\Blade\value()` helper, so future Statamic wrapper types are
  handled automatically. A bare non-string-backed `ArrayableString` now resolves
  via `->value()` instead of a string cast (string-backed fields are
  unaffected).

### Notes

- `json_encode()` on a relationship field now emits the augmented entry data
  rather than the empty objects the previous eager `Content` array produced.

## [2.0.0] - 2024-03-09

- Add support for Statamic 6
- Drop support for Statamic 5 and below
- Render Statamic {tags} from Latte {nodes} and (subexpressions)
- Normalize augmented values to iterables or objects
- Pass along paginator instances from tags and loops
- Render section and yield tags

## [1.3.0] - 2025-03-06

- Add support for Laravel 12

## [1.2.1] - 2025-01-06

- Pass default context into modifiers

## [1.2.0] - 2024-10-19

- Add support for Statamic 5 and Laravel 11

## [1.1.1] - 2024-04-29

- Fix private access error in Latte 3.0.14

## [1.1.0] - 2024-03-15

- Add helper function to fetch tag output

## [1.0.1] - 2024-03-11

- Expand test coverage

## [1.0.0] - 2024-03-09

- Initial release

[Unreleased]: https://github.com/daun/statamic-latte/compare/2.0.0...HEAD
[2.0.0]: https://github.com/daun/statamic-latte/releases/tag/2.0.0
[1.3.0]: https://github.com/daun/statamic-latte/releases/tag/1.3.0
[1.2.1]: https://github.com/daun/statamic-latte/releases/tag/1.2.1
[1.2.0]: https://github.com/daun/statamic-latte/releases/tag/1.2.0
[1.1.1]: https://github.com/daun/statamic-latte/releases/tag/1.1.1
[1.1.0]: https://github.com/daun/statamic-latte/releases/tag/1.1.0
[1.0.1]: https://github.com/daun/statamic-latte/releases/tag/1.0.1
[1.0.0]: https://github.com/daun/statamic-latte/releases/tag/1.0.0
