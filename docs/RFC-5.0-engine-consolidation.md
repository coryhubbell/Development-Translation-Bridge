# RFC 5.0 — Engine Consolidation

**Status:** Phases 1–2 shipped; Phase 3 next
**Created:** 2026-07-03

## Summary

Consolidate the PHP (v3) and Python (v4) engines onto a single shared
schema, and retire the lossy HTML-intermediate `translate` path. 5.0 ends
with one conversion semantics — the lossless universal-element model — with
both engines as conforming implementations of the same spec.

## Why now

The 4.7–4.11 line completed both prerequisites:

- **All 14 frameworks parse natively in Python** (parsers) and all 14 have
  Python converters — the lossless engine is feature-complete.
- **The PHP engine mirrors the same verified schemas** (real-format
  verification v4.4.0–v4.6.0) and shares the canonical responsive model.

What still differs is the *interchange shape*: PHP passes
`DEVTB_Component` objects (`type`/`attributes`/`content`/`children`);
Python passes Elementor-shaped element dicts
(`elType`/`widgetType`/`settings`/`elements`). Converters on each side
carry ad-hoc adapters for the other's vocabulary (e.g. the Python
Gutenberg converter's `_convert_component` translation layer). 5.0 removes
that seam.

## The canonical schema

The **universal element document** — normatively specified in
[`schema/universal-element.schema.json`](../schema/universal-element.schema.json)
— is the Python engine's element-dict shape, chosen because every converter
in both engines already consumes it (directly in Python; via adapters in
PHP):

```json
{
  "elements": [
    {
      "id": "abc123",
      "elType": "section | container | column | widget",
      "widgetType": "heading",
      "settings": {"title": "…", "header_size": "h2"},
      "elements": [],
      "isInner": true,
      "responsive": {"styles": {…}, "fields": {…}}
    }
  ],
  "version": "", "title": "", "meta": {}
}
```

Settings use the Elementor-style key vocabulary already shared by all 14
Python parsers (`title`/`header_size`, `editor`, `text` + `link{url,
is_external}`, `image{url, alt}`, `testimonial_*`, `tabs[]`, `icon_list[]`,
`html`, …). The responsive object is the canonical model from v4.5.0
(breakpoints `desktop`/`tablet`/`phone`, states `default`/`hover`).

## Phases

### Phase 1 — Spec + conformance harness *(shipped)*

- Publish the JSON Schema as the normative spec.
- Add `DEVTB_Component::to_universal()` so the PHP engine can emit the
  canonical shape.
- Add a **dual-engine conformance suite**: shared real fixtures (Elementor
  kitchen-sink, DIVI kitchen-sink, the real Breakdance export) are parsed
  by BOTH engines; outputs must validate against the schema and agree on
  extracted content. Runs in CI with the Python suite.

### Phase 2 — PHP interchange adoption *(shipped)*

- `DEVTB_Universal` (core) bridges both directions:
  `components_to_document()` and `document_to_components()`.
- Translator entry points: `parse_to_universal($content, $source)` and
  `translate_universal($document, $target)`.
- REST: `source`/`target` accept `universal` on `/translate` — send a
  universal document as input, or receive the parsed document as output.
- Cross-engine interchange is conformance-tested in both directions
  (Python-parsed → PHP-converted and PHP-parsed → Python-converted).
- The Python Gutenberg converter's ad-hoc component adapter is gone:
  `translation_bridge.interchange` mirrors
  `DEVTB_Component::to_universal()` exactly — the conformance suite
  asserts `component_to_element(to_array()) == to_universal()` on real
  fixtures — and the converter delegates to it. Collection vocabularies
  (`icon_list`, `wp_gallery`, `selected_icon`, `alert_*`, CTA links) now
  survive the component round trip in both engines.

### Phase 3 — Translate-path deprecation

- Every `translate` (HTML-intermediate) pair is re-routed through
  parse→universal→convert. The v3 mapping engine remains only for the
  HTML *content extraction* fallback.
- `devtb translate` becomes an alias for `transform` with a deprecation
  notice; fidelity metrics reported per conversion.

### Phase 4 — 5.0 release

- Remove the HTML-intermediate pipeline and the `DEVTB_Component`-shaped
  public interchange (breaking); `translate` alias retained for one minor.
- Single documented engine story: “one schema, two conforming runtimes.”

## Compatibility

Phases 1–2 are additive. Phase 3 changes internals but preserves CLI/REST
surfaces. Phase 4 is the 5.0.0 breaking release; deprecations land at
least one minor release ahead.

## Non-goals

- Dropping the PHP runtime (WordPress hosts need it — it stays as a
  conforming implementation).
- New framework coverage (orthogonal to consolidation).
