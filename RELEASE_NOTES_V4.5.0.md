# Translation Bridge 4.5.0 — Release Notes

**Release date:** 2026-07-03
**Theme:** Responsive breakpoint round-tripping

v4.5.0 closes the final v4.3.x roadmap item: tablet/phone breakpoints and
hover states now survive round trips for the three next-generation framework
paths (`divi-5`, `elementor-4`, `oxygen-6`), and responsive styling transfers
**across** frameworks through a canonical model.

---

## The canonical responsive model

A shared vocabulary — breakpoints `desktop` / `tablet` / `phone`, states
`default` / `hover` — carried in universal component metadata, implemented on
both engines:

- **PHP:** `DEVTB_Responsive_Helper`
  (`translation-bridge/utils/class-responsive-helper.php`)
- **Python:** `translation_bridge.responsive`

Desktop defaults keep flowing through content/attributes/styles exactly as
before; the canonical metadata only carries what a desktop-only reading would
lose. Unmappable breakpoints (Elementor `widescreen`, Breakdance landscape
variants) stay preserved verbatim in per-framework metadata.

### Per-framework mappings

| Framework | Native shape | Canonical mapping |
|---|---|---|
| DIVI 5 | Content wrappers `{"desktop": {"value", "hover"}, "tablet": …, "phone": …}` | states nest inside breakpoints; `value` ↔ `default` |
| Elementor 4 | Style-definition variants `{meta: {breakpoint, state}, props}` | `mobile` ↔ `phone`; state `null` ↔ `default` |
| Oxygen 6 | Design-tree leaves keyed `breakpoint_base` / `breakpoint_tablet_portrait` / `breakpoint_phone_portrait` | leaves flatten to dot-joined prop paths, re-nest on emit |

### What round-trips now

- **DIVI 5:** per-breakpoint content values and hover states on any content
  field (`text`, `innerContent`, `url`, …) parse into canonical form and
  re-emit as full multi-breakpoint wrappers.
- **Elementor 4:** style variants canonicalize per breakpoint/state and
  re-emit as one variant each; the parser now also populates component
  `styles` with desktop defaults (previously unset).
- **Oxygen 6:** design-section responsive values canonicalize and re-nest
  with `breakpoint_*` leaf keys — design data now round-trips at all
  (previously it parsed into metadata and never re-emitted).

### Cross-framework transfer

Because all three paths share the canonical model, responsive styling moves
between frameworks: Oxygen 6 design breakpoints become Elementor 4 variants
and vice versa. Covered by dedicated tests in both directions.

---

## Test coverage

- `tests/Unit/ResponsiveRoundTripTest.php` — 8 tests: per-framework
  canonicalization, same-framework round trips (emitted output re-parsed and
  compared field by field), and cross-framework transfers both directions.
- `tests/python/test_responsive.py` — 6 tests: helper shapes plus all three
  Python converters emitting multi-breakpoint output.

| | v4.4.0 | v4.5.0 |
|---|---|---|
| PHP tests | 311 / 4,818 assertions | **319 / 4,861 assertions** |
| Python tests | 133 | **139** |
| Failures / errors | 0 / 0 | **0 / 0** |
| Gutenberg e2e smoke | pass | **pass** |

## Compatibility

- Purely additive: elements without responsive data emit byte-identical
  output to v4.4.0. Desktop-only consumers are unaffected.
- No changes to the other 11 framework paths, the REST API, or the CLI
  surface.
- PHP 8.1+ / Python 3.9+ floors unchanged.

## Remaining known gap

An Oxygen 6-specific export fixture (vs. the Breakdance one) would close the
last stretch of schema uncertainty — contributions of real Oxygen 6 exports
remain welcome.
