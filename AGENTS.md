# AGENTS.md

Statamic 6 addon that integrates the **Latte** templating engine — adds `.latte` template support with custom tags, modifiers, caching, and layout resolution, plus Antlers fallback rendering.

## Stack

PHP 8.3+ · Laravel 13 · Statamic 6 · Latte via `miko/laravel-latte` 3.0 · Pest 4 · Pint 1.14 · PHPStan 3 (level 5). Tests use Orchestra Testbench.

## Commands

```bash
composer test                    # Pest
composer lint                    # Pint check (no changes)
composer format                  # Pint fix
composer analyse                 # PHPStan, --memory-limit=2G
```

## Test commands

```bash
composer test                                             # Run all tests
./vendor/bin/pest tests/Feature/ServiceProviderTest.php   # Run single file
./vendor/bin/pest --filter="name"                         # Run single test
```

## Architecture

- Namespace `Daun\StatamicLatte`, PSR-4
- Entry point `src/ServiceProvider.php` registers the Latte engine
- Latte extensions live in `src/Latte/Extensions/` (tags, modifiers, caching, layout resolution, Antlers inline rendering)
- Tests split across `tests/Feature/` and `tests/Unit/`

## Gotchas

- `nocache` tag works only with app-level static caching, not file-based
- Nested cache/nocache not supported
- PHPStan runs at level 5 with no generic type checking (performance)
