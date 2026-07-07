---
name: quality-gates
description: Validating changes in statamic-latte — the four composer gates (test/lint/format/analyse), CI matrix behavior, commit and PR conventions, CHANGELOG format, deprecation policy, and the release checklist. Use before committing, when a CI job fails, when adding a CHANGELOG entry, when cutting a release, or when planning a large refactor that needs the plan/review/report process.
---

# Quality gates, CI, and release process

This skill defines what "done" means for any change to this repo, how CI actually behaves (including its quirks), and the conventions for commits, changelog entries, deprecations, and releases. The project has no Makefile or task runner — `composer.json` `scripts` is the single command surface.

## When to use this skill

- Before committing any change (which gates to run, in what form).
- A CI matrix job failed and you need to know which cell does what.
- Adding a CHANGELOG entry or cutting a release.
- Deprecating or removing a public class (there is a hard policy here).
- Starting a large refactor (the project has a documented plan/review/report loop).
- Tempted to "clean up" composer.json, phpstan.neon, or ci.yml — read the invariants first.

## The four gates

All commands run from repo root. Defined in `composer.json` `scripts`.

| Command | Runs | Mode | Failure looks like |
|---|---|---|---|
| `composer test` | `./vendor/bin/pest` | full suite: 378 tests, ~19s | red `FAILED` blocks with file + test name; exit code 1 |
| `composer lint` | `./vendor/bin/pint --test` | check-only, changes nothing | `FAIL` per file with the rule name (e.g. `ordered_imports`); exit 1 |
| `composer format` | `./vendor/bin/pint` | applies fixes in place | never "fails" on style — it fixes; rerun `composer lint` to confirm |
| `composer analyse` | `./vendor/bin/phpstan analyse --memory-limit=2G` | static analysis of `src/` only | error table with file:line + identifier; exit 1 |

Extra test variants: `composer test:ci` (clover `coverage.xml`, what CI runs) and `composer test:coverage` (human-readable, needs xdebug/pcov).

Facts about the analyse gate (get these right — AGENTS.md is wrong here):

- The toolchain is **larastan 3.x on phpstan/phpstan 2.x** (verify anytime with `composer show | grep -iE 'phpstan|larastan'`). AGENTS.md says "PHPStan 3" — **no such major exists**; it conflates the larastan major with PHPStan. Don't go hunting for PHPStan 3 release notes.
- `phpstan.neon`: level 5, paths `src/` only (tests are not analysed), larastan extension included. Level 5 with no generic-type checking is an explicit **performance** decision recorded in AGENTS.md. Do not raise the level or add generics stubs without measuring analysis time and re-justifying the trade-off.
- The two `ignoreErrors` entries (`smaller.alwaysFalse`, `property.private`, both scoped to `src/Latte/Extensions/Nodes/Concerns/ExtractsToTemporaryView.php`) are NOT stale — they guard a Latte <3.0.14 compat branch. Leave them.
- Always use `composer analyse`, never bare `./vendor/bin/phpstan analyse` — the script hardcodes `--memory-limit=2G`; the bare run can exhaust default memory.

Fact about the lint gate: there is **no pint.json** anywhere — Pint runs the default Laravel preset, intentionally. Do not invent a config file.

## Definition of done

For ANY change, before it counts as finished:

1. Targeted tests for what you touched pass (`./vendor/bin/pest tests/Tags/CollectionTest.php` or `./vendor/bin/pest --filter="name"`).
2. `composer test` — full suite green.
3. `composer analyse` — zero errors.
4. `composer format` then `composer lint` — clean.

For multi-commit work this discipline is **per commit**, not per PR: `docs/report-data-layer-rebase.md` records all gates green after every step. CI never auto-fixes style (`pint --test` is check-only), so formatting must land in your commits.

Note: `composer analyse` is a **local-only gate** — CI does not run PHPStan. Skipping it locally means type errors ship silently.

## CI reality (.github/workflows/ci.yml)

One workflow ("CI"), triggered on every `pull_request` and on push to `main`. Single `tests` job, `fail-fast: true`, matrix:

- php: `[8.3, 8.4, 8.5]` × laravel: `[12.*, 13.*]` × statamic: `[6.*]` → 6 cells.
- `include` pins companions per Laravel line: Laravel 12.* → testbench 10.*, pest 3.*; Laravel 13.* → testbench 11.*, pest 4.*, plus `coverage: true` and `lint: true`.
- **Only the Laravel 13 rows run `composer run lint` and the Codecov upload.** A lint-only CI failure therefore always comes from a Laravel-13 cell. A test failure in only some cells usually means a Laravel 12 vs 13 / Pest 3 vs 4 incompatibility — avoid Pest-4-only APIs while Laravel 12 is in the matrix.
- Steps: checkout → setup-php (xdebug) → phpunit problem matcher → `composer require` the matrix versions with `--no-update` then `composer update` → conditional lint → `composer run test:ci` → conditional codecov/codecov-action@v4 uploading `./coverage.xml`.

Known cruft, safe to ignore (or clean deliberately, not incidentally):

- The three `exclude` rows pair statamic 4.*/5.* with laravel 11.*/12.*/9.* respectively. All are no-ops: an exclude row only fires if **every** key matches a cell, and statamic 4.*/5.* are no longer in the matrix — even though laravel 12.* itself is still a live matrix value. Leftovers from the pre-2.0 matrix.
- The install step requires `spatie/pest-plugin-snapshots:${{ matrix.snapshots }}` (pinned `2.*` in both include rows), but the package is **not in composer.json's require-dev** and no test currently calls snapshot assertions. It only exists in CI runs. Implication: snapshot assertions would pass in CI but fail locally until you add the package to composer.json — if snapshots ever reappear, add the dev dependency properly; if you remove the line from ci.yml, grep tests for `assertMatchesSnapshot` first.
- `phpunit.xml`'s `<source>` includes a nonexistent `./app` dir — harmless; coverage comes from `./src`.

## composer.json invariants

- `config.allow-plugins` lists `pixelfear/composer-dist-plugin`. This is **required** — statamic/cms uses it to download dist assets; removing it breaks `composer install`/`update` locally and in CI. Never prune it as "unused".
- Supported matrix must stay true in composer.json AND ci.yml simultaneously: php `^8.3`, statamic/cms `^6.0`, miko/laravel-latte `^3.0` (Laravel is constrained transitively, not pinned in `require`).
- No `version` field — Packagist reads git tags.

## Commit and PR conventions

Current convention (the one to follow): lowercase **conventional-commit prefixes** — `feat:`, `fix:`, `refactor:`, `perf:`, `docs:`, `chore:` — as in the most recent commits (`refactor: fold Normalizer into Content as wrap/wrapAll/unwrap statics`, `perf: defer augmentation of non-empty relationship fields at render boundary`, `docs: changelog + internals docs for data-layer rebase`).

Don't be confused by `git log`: the ~150 older commits use unprefixed sentence-case subjects ("Add support for section and yield", "Autoformat"). That style is historical; new work uses the conventional prefixes.

PR flow: feature branches named `feat/<topic>` (historically also `feature/`, `fix/`, `chore/`), PRs against `main`, short Title-Case PR titles, merged via GitHub merge commits (`Merge pull request #N from daun/<branch>`). Examples: PR #14 (`feat/compat`, "Compatibility checks"), #15 (`feat/slot-alias`, "Slot alias"). `gh pr view <N>` works in this repo and is the fastest way to recover the rationale behind an old change.

Branch state (as of 2026-07): remote `develop` and `feat/components` both exist. `git log main..origin/develop` is currently **empty** — develop is fully merged, but re-run that check before deleting it. `feat/components` has ~6 unmerged commits (the component-bridge work paired with `docs/antlers-blade-bridge-research.md`) — it is live work, not stale; never assume either branch is dead without checking `git log main..origin/<branch>`.

## CHANGELOG.md

Keep-a-changelog-ish. Structure: `## [Unreleased]` at top, `## [X.Y.Z] - YYYY-MM-DD` sections below, a link-reference block at the bottom (`[Unreleased]: .../compare/<latest>...HEAD` plus per-version release-tag URLs).

To add an entry: bullet under `## [Unreleased]`. Simple releases (1.x style) are plain bullet lists. Mixed releases use the current subsection style — `### Performance`, `### Changed (internal)`, `### Notes` — with multi-sentence bullets stating the user-visible change AND what is explicitly unchanged (model: the deferred-augmentation entry, which spells out that `{if $related}` truthiness is unchanged). Template-facing behavior changes always get a CHANGELOG line — the project's standard is zero silent behavior changes, even for internal refactors.

Known anomaly: `## [2.0.0] - 2024-03-09` carries the same date as 1.0.0 and sits above the 2025-dated 1.3.0; the GitHub release for 2.0.0 is actually dated 2026-06-14. The changelog date is a copy-paste placeholder. Do NOT rewrite history entries — just get the date right on the next release you cut.

## Versioning and deprecation policy

- Adding framework-version support = **minor** (precedent: 1.2.0 "Statamic 5 and Laravel 11", 1.3.0 "Laravel 12"). Dropping support = **major** (2.0.0 dropped Statamic ≤5).
- Outside framework support: new features and deprecations = **minor**; pure fixes = **patch**; behavior changes recorded under CHANGELOG `### Notes` count as minor unless they break documented template output (then major). E.g. the current Unreleased block (Normalizer deprecation + `json_encode()` note) is a 2.1.0, not a 2.0.1.
- **Deprecated public classes survive until the next major.** `src/Data/Normalizer.php` is a `@deprecated` delegating shim pinned for removal in 3.0. WHY: compiled `.latte` templates on user sites bake fully-qualified class names into the cached PHP (verify: `grep -rn '\\Daun\\StatamicLatte' src/` — the leading `\\` matches only the `\Daun\StatamicLatte...` FQCN literals emitted into compiled code, e.g. `TagNode.php`, `AttributeNormalizationExtension.php`, `Nodes/AntlersNode.php`, without the namespace/use noise a bare `Daun\\StatamicLatte` grep returns). Deleting a referenced class early fatals sites mid-upgrade with stale compiled templates. Corollary: after renaming any class, grep `src/` for its FQCN in string literals, not just PHP references.
- Tags are bare semver (`2.0.0`, no `v` prefix).

## Large changes: the plan → review → report loop

For substantial refactors, follow the documented process (worked example: the data-layer rebase — `docs/plan-data-layer-rebase.md` + `docs/report-data-layer-rebase.md`):

1. **Plan doc** at `docs/plan-<topic>.md`, self-contained: background & goal, required context with exact file paths, per-step instructions, acceptance criteria, gotchas, explicit non-goals, suggested commit sequence.
2. **Implement** on a `feat/<topic>` branch in small commits, all four gates green after each commit.
3. **Scored adversarial review** by an independent reviewer (the report shows subagent reviews scored /10 with SHIP-conditional verdicts); iterate until the reviewer is satisfied. This step caught two real correctness bugs in the rebase (`jsonSerialize` contract violation, `count()` divergence) — commit `770ba34` is the "address review feedback" fix.
4. **Fix blockers**, get a confirmation pass.
5. **Report doc** at `docs/report-<topic>.md`: status table, per-step gate results, commit list, diffstat, review findings, explicit "not done / out of scope" section. Land a final `docs:` commit with the report + CHANGELOG entries.

Small fixes don't need this ceremony — just the four gates and a CHANGELOG bullet if behavior is template-facing.

## Release checklist

Verified from repo evidence (tags 1.0.0–2.0.0, `gh release list` shows a GitHub release per tag, CHANGELOG link block):

1. Ensure CI is green on `main` (`gh run list --repo daun/statamic-latte --branch main --limit 1` — conclusion must be success) and `composer test && composer analyse && composer lint` pass locally.
2. In CHANGELOG.md: rename `## [Unreleased]` to `## [X.Y.Z] - YYYY-MM-DD` (use the real date — see anomaly above), add a fresh empty `## [Unreleased]` above it.
3. Update the bottom link block: `[Unreleased]` now compares `X.Y.Z...HEAD`; add `[X.Y.Z]: https://github.com/daun/statamic-latte/releases/tag/X.Y.Z`.
4. Commit (`docs:` prefix), tag `X.Y.Z` on main (bare semver, no `v`), push commit + tag.
5. Create the GitHub release for the tag (every past tag has one; UNVERIFIED whether past releases were created manually or via the Packagist/GitHub UI — there is no release automation in `.github/workflows/`, so assume manual `gh release create X.Y.Z`).
6. Packagist picks up the tag automatically (composer type `statamic-addon`, no version field in composer.json). UNVERIFIED: whether the Packagist webhook is configured; check packagist.org after tagging.

## Invariants — never do these

- Never remove `pixelfear/composer-dist-plugin` from `allow-plugins` — breaks statamic/cms installation.
- Never raise the PHPStan level, add generics checking, or expand `paths` casually — level 5 / src-only is a recorded performance decision.
- Never delete the two phpstan.neon `ignoreErrors` or the "dead" Latte <3.0.14 branch they guard — see CHANGELOG 1.1.1.
- Never delete a deprecated public class before the next major — compiled templates reference FQCNs.
- Never add a pint.json — default Laravel preset is the intended style.
- Never ship a template-facing behavior change without a CHANGELOG entry.
- Never assume `develop`/`feat/components` are stale without `git log main..origin/<branch>`.
- Never widen/narrow the support matrix in composer.json without mirroring ci.yml (and vice versa).

## Pitfalls

- **CI passes but analyse fails locally**: expected — PHPStan is not in CI. Run `composer analyse` yourself.
- **Lint failure only on some CI cells**: lint runs only on Laravel-13 rows; it's the same code, the other cells just skip the step.
- **Snapshot tests pass in CI, fail locally**: `spatie/pest-plugin-snapshots` is installed only by ci.yml's require line, not composer.json.
- **Adding a new Laravel line to CI**: copy the `include` row pattern (testbench/pest pins) and move the `coverage: true` + `lint: true` flags to the newest line.
- **Adding a CI-side quality gate**: follow the existing pattern — composer script calling `./vendor/bin/*`, wired as a workflow step, expensive single-run steps gated behind an `include` boolean like `lint`.

## How to verify a change to this subsystem

- Composer scripts: `composer test`, `composer lint`, `composer analyse` all exit 0 (378 tests at last count).
- ci.yml edits: push a branch and open a draft PR — the workflow runs on every `pull_request`; check all 6 matrix cells.
- CHANGELOG edits: confirm the link block resolves (compare URL for Unreleased, tag URL per version).
- Release: `git tag` shows the new bare-semver tag; `gh release list --repo daun/statamic-latte` shows the release; Packagist shows the version.

## Related skills

- **orientation** — repo layout and where the docs live.
- **testing** — test suite structure, fixtures, how to write targeted tests for gate 1.
- **debugging** — triaging a failing gate or CI cell beyond the mapping given here.
- **data-layer** — the Normalizer/Content deprecation story referenced in the version policy.
- **caching**, **tag-bridge**, **extensions-and-nodes**, **template-syntax** — subsystem skills whose changes these gates validate.
