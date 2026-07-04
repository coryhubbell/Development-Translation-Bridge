# Translation Bridge 4.13.0 — Release Notes

**Release date:** 2026-07-03
**Theme:** RFC 5.0 Phase 2 completes — one component vocabulary, two conforming implementations

v4.13.0 closes [RFC 5.0](docs/RFC-5.0-engine-consolidation.md) Phase 2: the
Python engine now translates the legacy `DEVTB_Component` shape through a
shared, spec-aligned interchange module that is conformance-tested to match
the PHP engine byte for byte. Phase 3 (translate-path deprecation) is next.

---

## The shared interchange module

- **`src/translation_bridge/interchange.py`** — the Python mirror of
  `DEVTB_Component::to_universal()`. Legacy component-shaped dicts
  (`type`/`attributes`/`content`/`children`, the pre-5.0 PHP interchange)
  translate to canonical universal elements in one place: structural
  mapping, the `UNIVERSAL_WIDGET_TYPES` table, the settings vocabulary,
  and responsive metadata carry-through, with PHP-semantics helpers so
  both engines agree on edge cases (`!empty`, `(int)`/`(string)` casts).
- **The Gutenberg converter's ad-hoc adapter is gone** — the ~115-line
  `_convert_component` attribute-translation layer (`label→text`,
  `image_url→image`, …) is now a thin delegate to the mirror. Structural
  components (`row`/`column`) convert to proper `core/columns` /
  `core/column` blocks instead of a flat group, and the schema-canonical
  `nav` widgetType is accepted.

## Exact-mirror conformance

The dual-engine conformance suite gains a third gate: for every component
the PHP engine parses out of the three real fixtures (Elementor
kitchen-sink, DIVI kitchen-sink, the real Breakdance export),

```
component_to_element(component.to_array()) == component.to_universal()
```

must hold exactly. The two engines can no longer drift on the component
vocabulary without CI failing.

## Round-trip vocabulary completed

Mirroring exposed five vocabularies that `settings_to_attributes()` emits
but `universal_settings()` could not read back. Both engines now round-trip
them through universal ⇄ component conversion:

- `icon_list` (icon-list items)
- `wp_gallery` (gallery images)
- `selected_icon` (icon widgets)
- `alert_title` / `alert_description`
- call-to-action `link` URLs

## Test coverage

| | v4.12.0 | v4.13.0 |
|---|---|---|
| Python tests | 196 | **237** (incl. 38 interchange + 3 exact-mirror) |
| PHP tests | 338 / 5,668 assertions | **339 / 5,673 assertions** |
| Failures / errors | 0 / 0 | **0 / 0** |
| E2e smoke gates | 3 × 2 engines — pass | **3 × 2 engines — pass** |

## Compatibility

- The component shape remains accepted everywhere it was before — it now
  translates through the shared vocabulary instead of an ad-hoc layer.
  Two intentional output improvements on that legacy path: `row`/`column`
  components produce real columns blocks, and quote-ish components render
  through the canonical testimonial vocabulary.
- The component-shaped interchange is deprecated in favor of universal
  documents and is scheduled for removal from the public surface in 5.0.
- PHP 8.1+ / Python 3.9+ floors unchanged.

## Roadmap position

Phase 2 is complete. Phase 3 re-routes every `translate` (HTML-intermediate)
pair through parse → universal → convert and makes `devtb translate` a
deprecated alias of `transform`; Phase 4 is the 5.0 breaking release.
