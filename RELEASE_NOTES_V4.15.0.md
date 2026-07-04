# Translation Bridge 4.15.0 — Release Notes

**Release date:** 2026-07-04
**Theme:** Pre-5.0 converter hardening — cross-source parity in Python

v4.15.0 closes the last gap before the 5.0.0 release: every Python
converter now consumes a universal document from **any** source parser
without losing content, matching the PHP engine's all-pairs coverage.

---

## The gate: a cross-source fidelity matrix

`tests/python/test_universal_matrix.py` holds 3 real fixtures (Elementor
kitchen-sink, DIVI kitchen-sink, the real Breakdance export) × 14 target
converters to a `measure_fidelity` floor of 0.9 per pair, in CI.

**Baseline: 34 of 39 pairs failed — several at 0%. Now: all 39 pass,
almost all at 100% content survival.**

## What was broken, and how it was fixed

- **Structural**: nested `section > container > column` shapes (how DIVI
  and Oxygen sources parse) collapsed to empty rows in most converters.
  All 14 now recurse structure correctly; bare widgets get valid column
  wrappers.
- **Input shape**: kadence and thrive consumed only the legacy component
  shape. `translation_bridge.interchange` is now **bidirectional**
  (`element_to_component` / `document_to_components` mirror
  `DEVTB_Universal`'s reverse direction), and those converters route
  universal input through it into their existing pipelines.
- **Vocabulary**: widget dispatch tables missed canonical widgetTypes
  (`text-editor`, `image-gallery`, `call-to-action`, `icon-list`,
  `price-table`, `alert`, …), silently dropping their content. All 14
  converters cover the canonical vocabulary, with content-preserving
  fallbacks for anything unmapped — no converter emits an empty element.

## Fidelity metric improvements

- Style-value keys (`title_color`, `heading_size`, …) no longer count as
  content strings.
- JSON-shaped outputs compare via decoded string scalars, so JSON
  escaping and multi-line strings never read as content loss.

## API notes

- `Elementor4Converter.convert()` returns the structured atomic node list
  (JSON-serializable) instead of a pre-encoded JSON string; CLI file
  output is unchanged.
- Gallery conversions preserve image URLs/alts rather than site-local
  media-library ids (ids render nothing after migration).

## Test coverage

| | v4.14.0 | v4.15.0 |
|---|---|---|
| Python tests | 262 | **306** (incl. the 39-cell matrix + reverse interchange) |
| PHP tests | 344 / 5,691 assertions | **344 / 5,691 assertions** |
| Failures / errors | 0 / 0 | **0 / 0** |
| E2e smoke gates | 3 × 2 engines — pass | **3 × 2 engines — pass** |

## Compatibility

- Additive except the `Elementor4Converter.convert()` return type (Python
  API consumers only; CLI behavior unchanged).
- PHP 8.1+ / Python 3.9+ floors unchanged.

## Roadmap position

This was the optional pre-5.0 tranche. Next: **Phase 4 — the 5.0.0
breaking release** (remove the HTML-intermediate pipeline and the
component-shaped public interchange; the `translate` alias survives 5.0
and is removed in 5.1).
