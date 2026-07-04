# Translation Bridge 5.0.0 — Release Notes

**Release date:** 2026-07-04
**Theme:** One schema, two conforming runtimes

5.0.0 completes the
[RFC 5.0 engine consolidation](docs/RFC-5.0-engine-consolidation.md).
Every conversion — every pair, both engines, all surfaces — rides one
lossless pipeline: **parse → universal document → convert**. The legacy
v3 mapping engine is removed.

---

## What 5.0.0 removes (breaking)

- **The v3 mapping engine**
  (`translation-bridge/core/class-mapping-engine.php`, the
  similarity-scoring mapper) and the translator's mapping-fallback
  branch. All 182 pairs were verified green on the universal route both
  before and after removal — nothing relied on the fallback.
- **The `DEVTB_Component` shape as an interchange format.** No public
  surface accepts or emits it (REST never did; the mapping engine was its
  last pipeline consumer). It survives only as the PHP engine's internal
  model, plus deprecated back-compat input to Python converters
  (reviewed in 5.1).

## What stays

- **Every CLI command, REST endpoint, and API signature.**
  `DEVTB_Translator::translate()`, `parse_to_universal()`,
  `translate_universal()`, `/translate` (including `universal` as REST
  source/target) are unchanged.
- **The `translate` alias** — deprecated since 4.14.0, retained through
  5.0 per the one-minor policy, removed in 5.1.
- **Fidelity metrics per conversion** (route + content-string survival);
  the content-survival check is now advisory, quantified by the stat.

## Migration guide

| If you… | Then… |
|---|---|
| use the CLI (`transform`, `translate`, `transform-site`, `analyze`) | Nothing changes. Move `translate` scripts to `transform` before 5.1. |
| call the REST API | Nothing changes. Prefer `universal` as source/target for programmatic interchange. |
| called `DEVTB_Mapping_Engine` directly | Use `parse_to_universal()` / `translate_universal()` — deterministic vocabulary translation, no confidence scoring. |
| read translator stats | `route` is always `universal`; `avg_confidence` is always `1.0`; `fidelity` quantifies content survival. |
| passed legacy component dicts to Python converters | Still accepted (deprecated). Move to universal documents; the shape is reviewed for removal in 5.1. |

## The consolidation, in numbers

- **Phases 1–2** (v4.12.0–v4.13.0): the normative JSON Schema, dual-engine
  conformance in CI, universal interchange in PHP, the exact-mirror
  Python interchange.
- **Phase 3** (v4.14.0): every `translate` pair re-routed through the
  universal document; fidelity metrics; deprecations.
- **Pre-5.0 hardening** (v4.15.0): Python cross-source parity — the
  39-cell fidelity matrix from a 34-failed baseline to all green.
- **Phase 4** (5.0.0): −962 lines; the mapping engine deleted.

## Test coverage

| | v4.15.0 | v5.0.0 |
|---|---|---|
| Python tests | 306 | **306** |
| PHP tests | 344 / 5,691 assertions | **344 / 5,691 assertions** |
| Failures / errors | 0 / 0 | **0 / 0** |
| E2e smoke gates | 3 × 2 engines — pass | **3 × 2 engines — pass** |

## Compatibility

- PHP 8.1+ / Python 3.9+ floors unchanged.
- WordPress theme/plugin surfaces unchanged.

## Roadmap position

RFC 5.0 is **complete**. 5.1 removes the `translate` alias and reviews
the remaining deprecated back-compat surfaces; further scope will be
driven by post-5.0 feedback.
