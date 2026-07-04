# Translation Bridge 5.1.0 — Release Notes

**Release date:** 2026-07-04
**Theme:** `transform-all` + the deprecation window closes

v5.1.0 executes [`docs/PLAN-5.1.md`](docs/PLAN-5.1.md): the replacement
lands first, then the deprecated surface is removed on schedule.

---

## New: `devtb transform-all`

```bash
./devtb transform-all divi page.txt
```

Fans one source out to **all 13 other frameworks** through the universal
route, writing one output per target and printing a per-target fidelity
table:

```
  ✓ gutenberg       26/26 content strings (100%) → page-gutenberg.html
  ✓ wpbakery        26/26 content strings (100%) → page-wpbakery.txt
  ✓ avada           25/26 content strings (96%)  → page-avada.html
  ...
```

Supports `--output-dir`, `--dry-run`, `--debug`.

## Removed: `translate` and `translate-all`

Deprecated since 4.14.0; retained through 5.0 per the one-minor policy.
Both commands now exit with a pointer to `transform` / `transform-all`.
The WordPress runtime engine (`DEVTB_Translator::translate()`) and every
REST endpoint are unaffected.

## Fixed

- `devtb` help no longer lists `list-frameworks` and `validate` as
  deprecated — they were never deprecated and remain supported.
- Framework output-extension map completed (kadence/thrive/divi-5 emit
  markup, not JSON).

## README overhaul (ease of use)

- **⚡ 30-second start** above the fold: clone → install → first
  conversion in three commands.
- **Choosing-a-command table** in Quick start.
- Usage sections now come **before** release history; the v4.x release
  narrative is collapsed into a details block. Screenshots and Mermaid
  diagrams retained.

## Test coverage

| | v5.0.0 | v5.1.0 |
|---|---|---|
| Python tests | 306 | **307** (incl. transform-all fan-out) |
| PHP tests | 344 / 5,691 assertions | **344 / 5,691 assertions** |
| Failures / errors | 0 / 0 | **0 / 0** |
| E2e smoke gates | 3 × 2 engines — pass | **3 × 2 engines — pass** |

## Compatibility

- Breaking only for scripts invoking the removed `translate` /
  `translate-all` CLI commands (deprecated for two minors) — swap in
  `transform` / `transform-all`.
- PHP 8.1+ / Python 3.9+ floors unchanged.

## Roadmap position

The RFC 5.0 consolidation and its follow-through are complete. No further
scope is planned; future work will be driven by post-5.0 feedback.
