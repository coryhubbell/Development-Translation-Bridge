# Translation Bridge 4.9.0 — Release Notes

**Release date:** 2026-07-03
**Theme:** Responsive canonicalization for Elementor v3 and Bricks

v4.9.0 ships roadmap item 3: Elementor v3 and Bricks Builder now feed the
canonical responsive model, completing responsive coverage across **all six
responsive-capable framework paths** (`divi-5`, `elementor-4`, `oxygen-6`
from v4.5.0; classic `oxygen` from v4.6.0; `elementor` and `bricks` here).

---

## What canonicalizes now

| Framework | Native responsive shape | Canonical mapping |
|---|---|---|
| Elementor v3 | Setting suffixes — `align_tablet`, `align_mobile`, `color_hover` | tablet/phone defaults; `_hover` → desktop hover state |
| Bricks | Setting-key suffixes — `_typography:tablet_portrait`, `_padding:mobile_portrait` | tablet/phone defaults; `mobile_landscape` (no canonical slot) passes through verbatim |

Both engines participate symmetrically:

- **Parsers canonicalize on parse** — PHP Elementor parser (sections and
  widgets), Python `ElementorParser` (a `responsive` field joins the element
  model), PHP Bricks parser, and the Python `BricksParser` from v4.7.0.
- **Converters re-emit on convert** — PHP + Python Elementor converters
  produce suffixed settings from canonical data (including through the
  "already Elementor-shaped" fast path, which previously bypassed responsive
  handling entirely); PHP + Python Bricks converters produce `:breakpoint`
  suffixed keys.

## Cross-framework transfer

Because everything meets in the canonical model, responsive data now moves
between any two responsive-capable frameworks: an Elementor tablet alignment
override becomes a Bricks `:tablet_portrait` key, a Bricks tablet typography
override becomes an Elementor `_tablet` suffix, and both can land in DIVI 5
wrappers, Elementor 4 style variants, or Oxygen `media` bags.

## Test coverage

4 new PHP round-trip tests (`ResponsiveRoundTripTest`, now 12) and 5 new
Python tests (`test_responsive.py`, now 11), including cross-framework
transfers in both directions.

| | v4.8.0 | v4.9.0 |
|---|---|---|
| PHP tests | 328 / 5,627 assertions | **332 / 5,640 assertions** |
| Python tests | 156 | **161** |
| Failures / errors | 0 / 0 | **0 / 0** |
| E2e smoke gates | 3 × 2 engines — pass | **3 × 2 engines — pass** |

## Compatibility

- Purely additive: content without responsive suffixes converts byte-
  identically to v4.8.0.
- No changes to the REST API or CLI surface. PHP 8.1+ / Python 3.9+
  floors unchanged.

## Roadmap position

One item remains on the 4.7+ list: Python parsers for the remaining
frameworks — the final stepping stone toward the speculative 5.x engine
consolidation.
