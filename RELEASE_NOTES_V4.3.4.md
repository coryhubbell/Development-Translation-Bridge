# DevelopmentTranslation Bridge v4.3.4

**Theme:** Closing the Elementor → Gutenberg widget-coverage gap.

## Why

A production client running an agent that ports content from foreign sites into Gutenberg was getting unusable output from Elementor scrapes — empty paragraphs, lost structure, dropped settings. Root cause: the Gutenberg converter's type map covered only ~21 of the 50+ universal types the Elementor parser produces. Everything else (`form`, `tabs`, `accordion`, `card`, `cta`, `counter`, `testimonial`, `pricing-table`, `alert`, `social-icons`, `slider`, `portfolio`, `countdown`, `map`, `progress`, `rating`, `nav`, etc.) silently collapsed to `core/paragraph` with whatever scrap of content happened to live in `$component->content` — usually empty.

## What changed

### PHP (`translation-bridge/converters/class-gutenberg-converter.php`)

- **Compound dispatch**: `tabs`, `accordion`, `card` (icon-box / image-box / flip-box), `cta`, `counter`, `testimonial`, `pricing-table`, `alert` are now expanded into a small group of native blocks (heading + paragraph + button, quote with citation, etc.) — wrapped in a `core/group` with a `devtb-<type>-converted` className so editors can spot and restyle them.
- **Marker fallback**: widgets with no native Gutenberg equivalent (`form`, `slider`, `countdown`, `portfolio`, `toc`, `map`, `progress`, `rating`, `unknown`) are preserved as `core/html` with a visible `<!-- devtb: unconverted <framework> widget "<type>" -->` comment plus a `data-devtb-source` annotation on a wrapping `<div>`. **No silent data loss.**
- **Type-map expansion**: added 1:1 mappings the parser already produces but the converter was missing — `social-icons` → `core/social-links`, `nav` → `core/navigation`, `blockquote` → `core/quote`, `icon` → `core/html`, `paragraph` → `core/paragraph`.
- **Content extraction** in `add_block_content` and `generate_inner_html` now reads the actual attribute names the Elementor parser produces (`image_url`/`alt_text`/`url`/`youtube_url`/`wp_gallery`/`icon_list`/etc.), not just `src`/`href`/`alt`. Lists/galleries/tables now emit real markup instead of empty defaults.
- **Canonical list shape**: `core/list` now contains `core/list-item` innerBlocks (WP 6.0+ shape), preserving the same canonical-correctness path v4.2.0 introduced for plain lists.
- **Unknown-type fallback flipped**: the previous fallback collapsed unknown types into `core/paragraph` (the production-bug source). It now routes to the marker handler.
- **`get_supported_types()`** updated to honestly report the new coverage (factory introspection is accurate).
- **`get_fallback()`** now goes through `convert_as_marker()` so the worst-case path also annotates rather than dumps content into an unmarked HTML block.

### Python (`src/translation_bridge/converters/gutenberg.py`)

Full rewrite of the v4 converter to mirror the PHP semantics one-to-one — same compound dispatch, same marker fallback, same widget coverage. Preserved the v4.2.0 canonical-correctness fixes:

- Block delimiters drop the `core/` namespace (`<!-- wp:heading -->`, not `<!-- wp:core/heading -->`).
- `core/heading` carries `class="wp-block-heading"` (WP 6.3+ canonical).
- `core/button` carries `class="wp-block-button__link wp-element-button"` (WP 6.1+ theme.json).
- `core/separator` carries `class="wp-block-separator has-alpha-channel-opacity"` (WP 6.5+).
- `core/column` width is a string with unit (`"50%"`), not a bare float.
- Paragraph alignment uses `textAlign`, not block-level `align`.
- `core/list` contains `core/list-item` innerBlocks (WP 6.0+ canonical).

Added `_denormalize_settings()` mirroring the PHP `denormalize_attributes()`: typography (font family/weight/line height), color (text/background), spacing (padding/margin quads), border (radius/color), className, anchor, textAlign — none of which the v4.1.0 Python converter projected, so they were silently dropped.

### Transform registry (`src/translation_bridge/transforms/registry.py`)

Four new transforms registered so the v4 CLI route is discoverable:

- `elementor_to_gutenberg`
- `html_to_gutenberg`
- `divi_to_gutenberg`
- `bricks_to_gutenberg`

All exposed via `GutenbergConverter` which is now exported from `src/translation_bridge/converters/__init__.py`.

### Tests

- **PHP**: new `tests/Unit/GutenbergWidgetCoverageTest.php` — 17 tests, 113 assertions. Asserts canonical `<!-- wp:X -->` form (no `core/`), compound dispatch produces the expected nested blocks, marker fallback surfaces a visible annotation comment, no widget type collapses silently, existing paragraph HTML is not double-wrapped, `wp-element-button` / `wp-block-heading` / canonical list-item shape preserved.
- **Python**: `tests/python/test_site_conversion.py::TestGutenbergConverter` extended from 5 to 21 tests — same shape as the PHP suite plus a transform-registration smoke test.

## Test results

- PHP `tests/Unit/FrameworkConversionsTest.php`: 200 tests / 3905 assertions — all green.
- PHP `tests/Unit/GutenbergWidgetCoverageTest.php`: 16 tests / 111 assertions — all green.
- Python `tests/python/`: 125 tests — all green.

## Out of scope (deliberate)

- **No new Gutenberg-as-source parser.** Scope here is moving content INTO Gutenberg, not pulling it OUT.
- **No new framework added to the matrix.** All 14 existing source frameworks stay as-is.
- **No new Gutenberg IR layer.** The architectural refactor toward a typed block tree (`blockName`/`attrs`/`innerBlocks`) is a future v5.x effort — this release closes the user-visible gap without changing the data model.

## Migration

None required. Existing transforms are unchanged. New transforms are additive. Output format upgrade (canonical `core/` stripping, `wp-block-heading`/`wp-element-button` classes, `core/list-item` innerBlocks) was already in place from v4.2.0.

## Post-merge fidelity follow-up

A kitchen-sink end-to-end smoke (`tests/smoke_gutenberg_e2e.py` against `tests/fixtures/elementor/kitchen-sink.json`) running the full set of widget categories through both engines caught two fidelity bugs in the initial PHP cut that the targeted unit tests didn't reach:

- **Counter title was dropped** in `convert_counter()` because the Elementor parser normalizes the widget's `title` setting to the universal `heading` attribute. The compound handler was only reading `attributes['title']`. Fixed by also reading `attributes['heading']`.
- **Blockquote author was dropped** in `generate_inner_html()` for `core/quote` because the cite-fallback chain didn't include `attributes['author']` (which the parser passes through unmapped from the blockquote widget). Fixed by adding `author` to the fallback chain.

The smoke harness has been kept in-tree so this widget matrix is exercised end-to-end against any future converter change, not just unit-tested in isolation. After the fixes: both engines produce balanced delimiters, zero empty-paragraph collapses, the expected 9 marker fallbacks for no-equivalent widgets, and every fixture title/body/cite survives the round-trip.
