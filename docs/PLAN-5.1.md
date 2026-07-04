# 5.1 Plan

**Status:** Proposed
**Created:** 2026-07-04 (post-5.0.0)

5.1 closes the deprecation window opened in 4.14.0 and tidies the surfaces
5.0 deliberately left alone. It is a small, sharply-scoped minor.

## 1. Remove the `translate` alias (the headline)

Deprecated since 4.14.0; retained through 5.0 per the one-minor policy.

- **`devtb` wrapper**: drop `translate` from routing and help.
- **PHP CLI**: remove `command_translate()`. The engine method
  `DEVTB_Translator::translate()` stays — it is the WordPress runtime's
  entry point, not the deprecated CLI surface.
- **Python CLI**: remove the `translate` argparse alias and its
  deprecation branch in `cmd_transform`.
- **Tests**: retire the alias tests in `test_translate_path.py`; the
  fidelity/gate tests stay (they pin `transform` behavior).
- **Docs**: README command table, CLI reference, examples.

## 2. Replace `translate-all` with `transform-all`

`translate-all` (PHP, one source → all 13 targets) rides the deprecated
surface but has no `transform` equivalent — removing it without a
replacement would regress a real workflow.

- **New `devtb transform-all <source> <file>`** in the Python CLI: loop
  all 13 targets through the universal route, write one output per
  target, print the per-target fidelity table.
- Then remove PHP `command_translate_all()`.

## 3. Fix the over-claimed deprecation heading

The `devtb` help currently lists `list-frameworks` and `validate` under
"deprecated — removed in 5.1". They were never RFC-deprecated and remain
useful. Move them to the supported section; they stay on the PHP CLI for
now (migrating them to Python is optional 5.2+ material, only if the PHP
CLI itself is ever slated to shrink).

## 4. Back-compat surfaces review (decision recorded)

- **Python converters' legacy component-dict acceptance: KEEP.** The
  reverse-interchange pipelines (kadence, thrive) use the component shape
  internally by design; the public tolerance costs nothing, is covered by
  tests, and removing it would churn 14 files for no user benefit. It
  remains undocumented and unsupported.
- **Translator stats `avg_confidence` (always 1.0): KEEP** through 5.x —
  removing a stats key breaks readers for zero gain. Documented as
  vestigial.
- **`cmd_transform`'s content-extraction last resort: KEEP** — it is the
  documented fidelity-gate fallback, not the removed HTML-intermediate
  pipeline.

## 5. Explicitly not in 5.1

- New framework coverage (orthogonal, as in RFC 5.0).
- Any 5.2 commitments: **no 5.2 scope exists today.** The consolidation
  roadmap is complete; anything beyond 5.1 should be driven by post-5.0
  user feedback, not planned speculatively.

## Estimated shape

One feature PR (alias removal + `transform-all` + help fixes + tests),
one release PR. Suite deltas: −2 alias tests, +`transform-all` tests.
