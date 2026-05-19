# Codex Review Summary — v4.3.0 Production-Readiness Fixes

**Branch:** `main` (uncommitted at time of writing; this PR squashes 10 logical steps)
**Baseline:** `8dd249b` (HEAD prior to this change)
**Scope:** Production fatal fix → matrix consistency → test green → deprecation cleanup

---

## TL;DR

| Metric                  | Before                              | After                                |
|-------------------------|-------------------------------------|--------------------------------------|
| CLI `./devtb translate` | **Fatal** (autoloader broken)       | **Works** end-to-end across 14 frameworks |
| Framework matrix        | 9 / 9 / 10 / 9 mismatched           | 14 everywhere (REST, CLI, admin, file-handler) |
| PHPUnit                 | 284 tests, **41 errors, 3 failures** | 284 tests, **0 errors, 0 failures**  |
| PHPUnit deprecations    | ~118 (implicit-nullable, float, setAccessible) | **0**                          |
| Python tests            | 109 passed                          | 109 passed (unchanged)               |
| Admin lint              | 2 errors, 1 warning                 | **Clean**                            |
| Admin build             | Passed (with stale fallback string) | Passed                                |
| Stale `claude` framework references | 7 files                  | 0                                    |
| Stale version strings   | 13+ `@version` doc comments + 4 metadata sites | All synced to 4.3.0       |

---

## Reviewer's Reading Order

Start here — the rest follows from these three foundations:

1. **`includes/class-devtb-autoloader.php`** (new) — the linchpin
2. **`includes/wp-function-stubs.php`** (new) — extracted from the test bootstrap
3. **`translation-bridge/core/class-converter-factory.php`** — new framework metadata helpers

Then ripple-out: `devtb-php`, `tests/bootstrap.php`, `functions.php`, then the API/CLI/File Handler consumers.

---

## Step-by-Step Change Index

### Step 1 — Shared autoloader (production fatal fix)

**Problem.** The CLI registered an inline `spl_autoload_register` that lowercased the namespace separator `\` and produced search paths like `translation-bridge/converters/class-devtb/translationbridge/converters/devtb-kadence-converter.php` for `DEVTB\TranslationBridge\Converters\DEVTB_Kadence_Converter`. The actual file is `class-kadence-converter.php`. Every namespaced converter failed to load → `./devtb translate bootstrap kadence ...` threw "class not found."

**Fix.** New file `includes/class-devtb-autoloader.php`:
- Strips the namespace prefix; keeps only the short class name.
- Lowercases + kebab-cases; strips the conventional `devtb-` prefix.
- Searches 8 conventional locations (handling both `class-X.php` and `interface-X.php` naming and the `class-devtb-X.php` convention used in `includes/`).
- Special-cases the `-interface` suffix: `DEVTB_Parser_Interface` → file `interface-parser.php` (not `interface-parser-interface.php`).
- `devtb_register_autoloader()` is idempotent via a static flag.

**Wiring.**
- `/devtb-php`: replaces the broken inline autoloader.
- `/tests/bootstrap.php`: registers after Composer autoload (covers `DEVTB_Mapping_Engine` which has no PSR-4-compliant filename).
- `/functions.php`: registers inside `devtb_init_translation_bridge()` as defense-in-depth so any future converter missed by the glob-loader still resolves at runtime.

**Why not Composer classmap.** Would work, but requires `composer dump-autoload` after every converter add/rename — a deployment-time footgun for a WP theme that may be deployed via SFTP/git pull without composer.

**Verification.** `./devtb translate bootstrap kadence /tmp/smoke.html` now completes (was fatal pre-change).

### Step 1b — Shared WP function stubs

**Problem.** Once the autoloader started successfully loading converters, the CLI hit a second fatal: `Call to undefined function DEVTB\TranslationBridge\Utils\esc_attr()` because PHP looks up unqualified function calls inside a namespace then falls back to global — but WordPress's `esc_attr` doesn't exist outside a WP runtime.

**Fix.** Extracted ~270 lines of WP function stubs from `tests/bootstrap.php` into `includes/wp-function-stubs.php`. Both the CLI entrypoint and the PHPUnit bootstrap now load the same file. Coverage expanded to include `is_wp_error`, `wp_schedule_single_event`, the `*_IN_SECONDS` constants, `sanitize_key`, `wp_unslash`, `absint`, etc. — everything the runtime actually touches.

All function definitions are guarded with `function_exists` / `class_exists` / `defined`, so the file is safe to load alongside a real WordPress environment.

**`tests/bootstrap.php`** shrinks from 380 lines to ~38 (the rest now lives in the shared file).

### Step 2 — Framework matrix consistency (14 everywhere)

**Source of truth.** `DEVTB_Converter_Factory::get_supported_frameworks()` already returned the 14 correct slugs. Added three constants on the factory + one helper:

```php
public const FRAMEWORK_DISPLAY_NAMES = [ /* slug => display name */ ];
public const FRAMEWORK_FORMATS       = [ /* slug => html|json|shortcodes|block */ ];
public const FRAMEWORK_FILE_EXTENSIONS = [ /* slug => extension */ ];
public static function get_framework_info(): array; // computed: name + cms_version + format + extension + file_extensions
```

The new helper is the single source-of-truth for REST + CLI + future admin sync. CMS versions are pulled live from each converter's `get_target_cms_version()`.

**Consumers rewired:**

| File | Before | After |
|---|---|---|
| `/includes/class-devtb-api-v2.php` | Hardcoded 9-framework array; emitted `type`+`extension`; `total_frameworks=9`, `pairs=72` | Lazy-init from factory in constructor; emits `format`+`file_extensions` (matches test spec); auto-computed 14/182 |
| `/includes/class-devtb-cli.php` | Hardcoded 9-framework map `'bootstrap' => 'Bootstrap 5.3.3'`... | Populated in constructor via `build_framework_labels()` from factory |
| `/includes/class-devtb-file-handler.php` | 9 entries + bogus `'claude'` | 14 entries; `detect_framework()` claude-branch removed and replaced with kadence-detection |
| `/includes/class-devtb-config.php` | 9 + `'claude'`, stale display names, `VERSION='3.2.1'` | 14, accurate display names, `VERSION='4.3.0'` |
| `/admin/src/types/index.ts` | 10 entries incl. `'claude'` | 14 entries |
| `/admin/src/components/Layout/FrameworkSelector.tsx` | 10 rows incl. `'claude'` | 14 rows; added TODO to source from `/devtb/v2/frameworks` for auto-sync |
| `/admin/src/components/Monaco/MonacoEditor.tsx` | `Record<Framework,string>` with `'claude'` entry | 14-entry Monaco language map |
| `/includes/class-devtb-wpbakery-templates.php` | Compatibility-score map with `'claude' => 0.95` | Updated to 13 framework targets (drop claude, add divi-5/elementor-4/oxygen-6/kadence/thrive) |

**Tests touched in lock-step (would have flipped green→red otherwise):**
- `tests/Unit/APIv2Test.php` — `assertCount(9, …)` → 14; expected-frameworks list → 14
- `tests/Unit/CLITest.php` — same shape
- `tests/Integration/TranslationBridgeIntegrationTest.php` — three `'claude'` targets repointed to `gutenberg`; `test_all_frameworks_as_targets` now exercises all 13 non-bootstrap targets

**API contract change.** `GET /wp-json/devtb/v2/frameworks` framework records:
- Before: `name, type, extension, description`
- After: `name, description, format, extension, file_extensions, cms_version`

The `format` + `file_extensions` keys match what `APIv2Test::test_framework_info_structure` (line 207-208) explicitly asserts. Per Explore: no external consumer was found that binds to the old `type` key, so this is safe to flip.

**Trap.** `get_status()` previously returned only `version: '2.0'`. The test `test_status_endpoint_returns_api_info` asserts `$data['api']['name'] === 'devtb'` and `$data['api']['version'] === 'v2'`. Added an `api: { name, version }` sub-object alongside the existing `version` field so we don't break any other client that reads `version` at the top level.

**Trap.** `get_job_status` error code was `'job_not_found'`; test (line 194) expected `'devtb_job_not_found'`. The `devtb_` prefix matches the codebase's other error-code conventions (`devtb_auth_missing_key`, etc.), so the test was right.

### Step 3 — CLI argument parser fix

**Bug.** `parse_arguments()` greedily consumed the next arg as a flag value whenever a long flag wasn't followed by an option-prefixed arg. So `--dry-run divi` became `options['dry-run'] = 'divi'` instead of `true`, and `divi` was dropped from positionals. Test `test_mixed_positional_and_options_parsing` (CLITest.php:199) caught this.

**Fix.** Added `const BOOLEAN_FLAGS` enumerating 10 known boolean long-flags (`dry-run`, `debug`, `verbose`, `ai-ready`, `force`, `help`, `version`, `quiet`, `no-color`, `json-output`). For long flags in the set, the parser sets `true` and never consumes the next arg.

**Considered and rejected:** Adding a `BOOLEAN_SHORT_FLAGS` set. `-d` is the short for both `--debug` (boolean) and `--output-dir` (value) — call sites:
- `class-devtb-cli.php:396`: `has_option('debug', 'd')`
- `class-devtb-cli.php:448`: `get_option('output-dir', 'd')`

Treating `-d` as strictly boolean would break `-d /path/to/dir` usage. Disambiguating short flags requires per-command schemas, which is out of scope. Short-flag parsing stays greedy with an explanatory inline comment. The failing test only uses long flags, so this is enough.

### Step 4 — File Handler missing methods + extension bug

**Bug.** Test `test_get_extension_returns_correct_extension` (FileHandlerTest.php:121) called `get_extension('file.txt')` expecting `'txt'` (filesystem-extension extraction), but the production method does framework-slug → extension map lookup, returning `'html'` (the default fallback).

**Fix.** Did NOT change `get_extension()` semantics — it has production callers in output-filename generation that depend on framework lookup. Added a new method `get_file_extension(string $filename): string` that does `pathinfo($filename, PATHINFO_EXTENSION)` and lowercases the result. Pointed the test at the new method.

**Missing methods (errored in PHPUnit).** Added:
- `format_file_size(int $bytes): string` — produces `0 B`, `1 KB`, `1.5 KB`, `1 MB`, ... per the test's expected values; uses `fmod($value, 1.0) === 0.0` to drop decimal point when whole; trims trailing zeros otherwise.
- `find_files(string $dir, string $pattern = '*'): array` — thin alias of the existing `list_files()` (preserves test intent without duplicating implementation).

### Step 5 — Transient + time WP mocks

Folded into Step 1b. The shared `wp-function-stubs.php` now provides:
- In-memory `$GLOBALS['__devtb_stub_transients']` backing `get_transient` / `set_transient` / `delete_transient`.
- `current_time($type)` returning `time()` for `timestamp`/`U`, otherwise a `Y-m-d H:i:s` string (or GMT variant if `$gmt` truthy).

The same arrangement covers `$GLOBALS['__devtb_stub_options']` for `get_option`/`update_option`/`delete_option`.

20+ APIv2Test/AuthTest/RateLimiterTest errors that originated from these missing functions are now passing.

### Step 6 — AuthTest int args

**Bug.** Tests called `$this->auth->generate_api_key('test_user')` with a string, but the signature is `int $user_id`. The fix is to change the test arguments to integers (which is what WordPress user IDs are semantically).

**Bonus discovery.** `generate_api_key()` returns an array `['key' => ..., 'user_id' => ..., 'name' => ..., 'permissions' => [...], ...]`, not a string. The test asserted `assertIsString($key)`. Updated the test to assert array shape + extract `$result['key']` for the string assertions.

### Step 7 — Version metadata

Mechanical pass:
- `/devtb-php`: header comment "10 page builder frameworks" → "14"; `@version 4.0.0` → 4.3.0; `define('DEVTB_VERSION', '4.0.0')` → 4.3.0 (also wrapped in `!defined()` guard).
- `/admin/package.json`: `0.0.0` → `4.3.0`.
- `/includes/class-devtb-config.php`: `VERSION = '3.2.1'` → `'4.3.0'`; `@version` doc → 4.3.0.
- `/includes/class-devtb-visual-interface.php`: fallback `'3.2.2'` → `'4.3.0'`.
- `/includes/class-devtb-api-v2.php` + `class-devtb-file-handler.php` + 11 other includes: `@version` doc → 4.3.0.
- `/composer.json`: added `"version": "4.3.0"`.

All `define()` calls in `devtb-php` and `tests/bootstrap.php` wrapped in `!defined()` guards to silence the PHP 9 "constant already defined" deprecation when CLI bootstrap and theme bootstrap intersect.

### Step 8 — Admin lint fixes

**Three findings, three fixes:**

1. **`admin/src/components/Layout/Toolbar.tsx:119`** — `(window as any).devtbData?.version || '3.3.0'`. Created `admin/src/types/wp-globals.d.ts` with a typed `Window.devtbData` declaration matching the PHP `render_page()` payload exactly. Cast removed. Fallback updated to `'4.3.0'`.

2. **`admin/src/services/api-client.ts:157`** — `case 429:` body had `const retryAfter = ...` causing `no-case-declarations`. Wrapped the body in `{ … }` to scope the binding.

3. **`admin/src/components/SideBySideEditor.tsx:28`** — `useEffect` had missing dependencies `isTranslating` + `translateCode`. Verified that `translateCode` is a Zustand store action (stable reference) and that adding `isTranslating` to deps would re-fire the effect every time the in-flight flag toggled (potential debounce-amplification loop).

   Refactored: the effect now reads both `translateCode` and the in-flight guard via `useEditorStore.getState()` at fire-time, so neither is a dependency. The destructure still pulls `isTranslating` from the store hook because the JSX further down (`{isTranslating && <Translating…>}`) does need to react to changes.

### Step 9 — PHP 8.5 deprecation cleanup

**Implicit-nullable params (typed param with default `null`, no `?`):**
- `class-devtb-rate-limiter.php:159` — `int $duration = null` → `?int $duration = null`
- `class-devtb-corrections.php:522` — `array $auto_fix = null` → `?array $auto_fix = null`

Note: most matches from initial scan were untyped `$default = null` patterns which aren't deprecated. Only typed params trigger the warning.

**Float-array-key deprecation.** PHP 8.5 deprecates silent float-to-int casting on array keys. Found three converters using float literals as keys for percentage→column-type mapping. Changed to string keys with explicit `(float)` cast at the comparison site:

- `class-divi-converter.php:508-517`
- `class-wpbakery-converter.php:504-521`
- `class-avada-converter.php:455-468`

Pattern:
```php
// Before (66.66 silently becomes int(66) — wrong + deprecation warning):
$map = [ 66.66 => '2_3', 33.33 => '1_3', ... ];
foreach ($map as $pct => $type) { $diff = abs($width - $pct); ... }

// After:
$map = [ '66.66' => '2_3', '33.33' => '1_3', ... ];
foreach ($map as $pct => $type) { $diff = abs($width - (float)$pct); ... }
```

**`ReflectionMethod::setAccessible()` (deprecated as no-op since 8.1):** Removed 18 call sites across `tests/Unit/AuthTest.php` and `tests/Unit/CLITest.php` via `perl -ne 'print unless /->setAccessible\(true\);/'`. All target reflection on properties or methods that are publicly-accessible-by-default in 8.1+.

**Final deprecation count during full `vendor/bin/phpunit` run: 0.**

---

## Files Touched

### New (3)
- `includes/class-devtb-autoloader.php`
- `includes/wp-function-stubs.php`
- `admin/src/types/wp-globals.d.ts`

### Modified (37)
- PHP bootstrap: `devtb-php`, `functions.php`, `composer.json`, `tests/bootstrap.php`
- Factory: `translation-bridge/core/class-converter-factory.php`
- Consumers: `includes/class-devtb-api-v2.php`, `class-devtb-cli.php`, `class-devtb-file-handler.php`, `class-devtb-config.php`, `class-devtb-visual-interface.php`, `class-devtb-wpbakery-templates.php`
- Deprecation cleanup: `class-devtb-rate-limiter.php`, `class-devtb-corrections.php`, three converters (`divi`, `wpbakery`, `avada`)
- Doc-version bumps (11 files): `class-devtb-auth.php`, `class-devtb-claude-api.php`, `class-devtb-element-registry.php`, `class-devtb-encryption.php`, `class-devtb-job-queue.php`, `class-devtb-logger.php`, `class-devtb-persistence.php`, `class-devtb-webhook.php`, `class-devtb-wpbakery-advanced.php` (and the consumers above also had their `@version` bumped)
- Admin: `package.json`, `src/types/index.ts`, `src/components/Layout/FrameworkSelector.tsx`, `src/components/Layout/Toolbar.tsx`, `src/components/Monaco/MonacoEditor.tsx`, `src/components/SideBySideEditor.tsx`, `src/services/api-client.ts`
- Tests: `tests/Unit/APIv2Test.php`, `AuthTest.php`, `CLITest.php`, `FileHandlerTest.php`, `tests/Integration/TranslationBridgeIntegrationTest.php`

---

## Verification Commands (run, results inline)

```bash
# PHP syntax across the repo
find . -name "*.php" -not -path "./vendor/*" -not -path "./node_modules/*" -print0 \
  | xargs -0 -n1 php -l 2>&1 | grep -v "No syntax errors"
# → empty output
```

```bash
vendor/bin/phpunit
# → OK (284 tests, 4133 assertions)
```

```bash
php -d error_reporting=E_ALL vendor/bin/phpunit 2>&1 | grep -ic deprecated
# → 0
```

```bash
python3 -m pytest tests/python -q
# → 109 passed
```

```bash
cd admin && npm run lint
# → ESLint: No issues found
cd admin && npm run build
# → built in 539ms; bundle 97.87 kB gzip
```

```bash
./devtb --version                    # DevelopmentTranslation Bridge v4.3.0
./devtb list-frameworks               # Supported Frameworks (14 Total); pairs=182
./devtb translate bootstrap kadence   /tmp/smoke.html --dry-run  # ✓
./devtb translate bootstrap thrive    /tmp/smoke.html --dry-run  # ✓
./devtb translate bootstrap oxygen-6  /tmp/smoke.html --dry-run  # ✓
./devtb translate bootstrap divi      /tmp/smoke.html --dry-run  # ✓ (--dry-run honored, no greedy consumption)
```

```bash
grep -rn "'claude'" --include="*.php" --include="*.ts" --include="*.tsx" includes admin/src translation-bridge tests
# → no matches (full purge confirmed)
```

---

## Reviewer Hot Spots

If you only have time to look at three things:

1. **`includes/class-devtb-autoloader.php`** — does the location-search list cover all naming conventions? Are there edge cases (e.g., a future converter with no `DEVTB_` prefix in its class name)? The `-interface` suffix special-case is the trickiest bit.

2. **`translation-bridge/core/class-converter-factory.php::get_framework_info()`** — instantiates every converter to read `get_target_cms_version()`. This is called from `DEVTB_API_V2::list_frameworks` (public REST endpoint, `permission_callback` is `__return_true`). Is the instantiation cost acceptable, or do we want a static `FRAMEWORK_CMS_VERSIONS` constant?

3. **`includes/class-devtb-api-v2.php::list_frameworks`** — the response shape changed (`type` → `format`, added `file_extensions`). Confirm no external consumer (Postman collections, partner integrations, docs) depends on the old keys. The admin UI doesn't currently consume this endpoint (the `FrameworkSelector` is still hardcoded), so the only known consumer is the PHPUnit suite.

---

## Known Non-Regressions Carried Forward

- **`includes/class-devtb-claude-api.php`** still references a non-existent unnamespaced `Translator` class on lines 84 and 335. This file was broken before this change — no caller exercises those methods (only `is_api_available()` and `get_corrections()` are reached from `class-devtb-corrections.php`). Out of scope; would be a separate ticket. The `'claude'` framework slugs inside that file are now meaningless but harmless since the methods are unreachable.

- **Admin `FrameworkSelector`** still hardcodes the 14-framework array. A TODO comment was added pointing to `/devtb/v2/frameworks` as the eventual source of truth. Same for `MonacoEditor`'s language map. Per the plan: "no new abstractions unless they're a clear win" — wiring up runtime fetch + loading states is a meaningful refactor.

- **Browserslist data is 6 months old**, baseline-browser-mapping is 2 months old. Vite emits a soft warning. Updating these is a one-line npm command but it's a dependency-floor change that should be its own commit.
