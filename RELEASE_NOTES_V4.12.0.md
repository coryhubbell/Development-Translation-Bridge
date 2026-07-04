# Translation Bridge 4.12.0 — Release Notes

**Release date:** 2026-07-03
**Theme:** The 5.x chapter opens — canonical schema + universal interchange

v4.12.0 ships the first two phases of
[RFC 5.0](docs/RFC-5.0-engine-consolidation.md), the engine-consolidation
plan that ends with one schema and two conforming runtimes.

---

## Phase 1 — the spec and its enforcement

- **`schema/universal-element.schema.json`** normatively specifies the
  canonical universal element document — the Python engine's element-dict
  shape, chosen because every converter in both engines already consumes
  it. It covers the elType/widgetType vocabulary, the Elementor-style
  settings keys all 14 parsers emit, and the v4.5.0 responsive model.
- **`DEVTB_Component::to_universal()`** — the PHP engine emits the
  canonical shape.
- **Dual-engine conformance suite** — the Elementor kitchen-sink, DIVI
  kitchen-sink, and the real Breakdance export are parsed by BOTH engines
  on every CI run; outputs must be schema-valid and content-equivalent.
  The engines can no longer drift apart silently.

## Phase 2 (core) — universal interchange in PHP

- **`DEVTB_Universal`** bridges both directions:
  `components_to_document()` and `document_to_components()`.
- **Translator entry points:** `parse_to_universal($content, $source)`
  and `translate_universal($document, $target)`.
- **REST:** `/translate` accepts `universal` as source (send a canonical
  document, receive target output) or target (receive the parsed canonical
  document) — external systems can exchange the interchange format with a
  WordPress install directly.
- **Cross-engine proof:** conformance tests convert a Python-parsed
  document in the PHP engine and a PHP-parsed document in the Python
  engine — both directions preserve content.

## Test coverage

| | v4.11.0 | v4.12.0 |
|---|---|---|
| Python tests | 185 | **196** (incl. 11 conformance) |
| PHP tests | 332 / 5,640 assertions | **338 / 5,668 assertions** |
| Failures / errors | 0 / 0 | **0 / 0** |
| E2e smoke gates | 3 × 2 engines — pass | **3 × 2 engines — pass** |

## Compatibility

- Purely additive: new schema file, new interchange class/methods, new REST
  enum value. No existing behavior changes.
- PHP 8.1+ / Python 3.9+ floors unchanged.

## Roadmap position

Phase 2 has one named remainder (migrating the Python Gutenberg converter's
ad-hoc component adapter onto the shared vocabulary); Phase 3 re-routes
every `translate` pair through the lossless path; Phase 4 is the 5.0
breaking release.
