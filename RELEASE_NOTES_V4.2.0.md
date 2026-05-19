# Translation Bridge 4.2.0 — Release Notes

**Release date:** 2026-05-18
**Theme:** Re-association with current CMS versions + Kadence and Thrive Themes support

This is the final 4.x release. It updates every existing converter to target the
current stable version of its upstream CMS, fixes correctness bugs surfaced
during the audit, and adds two new converters bringing total framework
coverage from 9 to 11.

---

## New converters

### Kadence (`kadence`)

- Targets **Kadence Blocks 3.7.2** and **Kadence Theme 1.5.0**
- Covers Kadence Blocks plugin, Kadence Theme template parts (header/footer/
  template builder), and Kadence Pro premium blocks
- Emits `kadence/rowlayout → kadence/column → leaf` block structure with the
  required `uniqueID` on every kadence-namespaced block
- Body text/list/quote fall through to canonical `core/*` blocks so output
  round-trips cleanly through Gutenberg
- Factory aliases: `kadence`, `kadence-blocks`, `kadenceblocks`

### Thrive Themes (`thrive`)

- Targets **Thrive Architect 10.8.10** and current Thrive Theme Builder
- Covers Thrive Architect, Theme Builder template parts, and Thrive Suite
  extras (Leads, Quiz Builder, Apprentice, Ultimatum)
- Emits TCB (Thrive Content Builder) HTML with per-element `data-css` tokens
  and a trailing `<style class="tve_custom_style">` block that resolves them
- Suite extra shortcodes (`[thrive_leads ...]`, `[tcb-quiz ...]`,
  `[thrive_ultimatum ...]`, `[tva_*]`) are detected and passed through
  unchanged in a `tcb-shortcode-passthrough` wrapper
- Factory aliases: `thrive`, `thrive-themes`, `thrive-architect`,
  `thrive-theme-builder`

---

## Converter version re-association

Each existing converter now declares an explicit `TARGET_CMS_VERSION` constant
(PHP) and module-level variable (Python). The interface contract
`DEVTB_Converter_Interface` adds `get_target_cms_version(): string` so callers
can detect drift.

| Framework | Target | Notes |
|---|---|---|
| Bootstrap | `5.3.8` | |
| Elementor | `3.30.0` | 4.x Atomic Editor → passthrough (see below) |
| DIVI | `4.27.0` | 5.x block engine → passthrough (see below) |
| Avada | `7.15.3` | |
| WPBakery | `8.7.3` | |
| Beaver Builder | `2.10.2` | |
| Bricks | `2.3.5` | |
| Oxygen | `4.8.3` | Oxygen 6 is a ground-up rewrite — deferred to 4.3 |
| Gutenberg (WP core) | `6.9.0` | |
| Kadence | `3.7.2` | (new) |
| Thrive | `10.8.10` | (new) |

---

## Audit findings — correctness fixes

### Gutenberg

- **Canonical serialization restored.** Both PHP and Python now emit
  `<!-- wp:paragraph -->` instead of `<!-- wp:core/paragraph -->`. The `core/`
  namespace is stripped on serialization (matches existing test fixtures and
  WordPress's own output).
- **Heading block** now emits the required `class="wp-block-heading"`
  (canonical since WP 6.3). Without it WP 6.7+ marks the block invalid.
- **Button block** now emits `wp-element-button` class (required by the
  theme.json element-styling pipeline since 6.1).
- **Separator block** now emits `has-alpha-channel-opacity` (canonical since
  6.5; without it the separator re-renders and loses opacity).
- **`align` → `textAlign`** for paragraph/heading text alignment. `align`
  means *block-level* alignment in canonical 6.5+ schema (e.g. wide/full); text
  alignment is `textAlign`. Emitting `align` for text was producing
  mis-rendered blocks.
- **Deprecated attributes removed.** PHP converter no longer emits
  `customTextColor`, `customBackgroundColor`, `customFontSize`. These were
  canonical pre-5.8; current schema uses `style.color.*` and
  `style.typography.fontSize` (already built alongside, causing duplicate
  conflicting attribute pairs).
- **Column width** emitted as string with unit (`"50%"`) instead of bare float.
- **`core/list`** now wraps each item in `core/list-item` innerBlocks
  (canonical since WP 6.0; flat HTML still parses but triggers a deprecation
  migration on first edit).

### Elementor

- **`cta` widget mapping changed** from `call-to-action` (Pro-only) to
  `icon-box` (free-core compatible). The previous mapping was a pre-existing
  latent bug that produced "widget not found" placeholders on free-Elementor
  sites.
- **Atomic Editor (v4) detection** added. New `is_atomic_v4_payload()` helper
  in both `translation-bridge/parsers/class-elementor-parser.php` and
  `src/translation_bridge/parsers/elementor.py` detects v4 content by
  `elType` prefix (`e-*`) or per-element `version >= 4`. Callers should route
  v4 content to passthrough. Full v4 native support is planned for 4.3.

### Bricks

- **`parent` field** now emitted as a string (`"0"`) in the Python converter.
  Previously emitted as `int 0`, which is rejected by Bricks 2.x's stricter
  import validation.
- **`testimonial` mapping** fixed in PHP converter — Bricks' element key is
  `testimonials` (plural), not `testimonial`.
- **`gallery` mapping** aligned between PHP and Python — both now map to
  `image-gallery`. PHP previously mapped to `carousel`.

### DIVI

- No 4.x track drift found.
- **DIVI 5 detection** added. New `is_divi5_payload()` static method in
  `translation-bridge/parsers/class-divi-parser.php` and
  `src/translation_bridge/converters/divi.py` detects DIVI 5 block-based
  content (regex match on `<!--\s*/?wp:divi/`). `is_valid_content()` now
  returns `false` for DIVI 5 payloads so callers route to passthrough. Full
  DIVI 5 native support is planned for 4.3.

### Oxygen

- **`TARGET_CMS_VERSION` downgraded** from claimed `6.1.0` to `4.8.3`. The
  converter emits the legacy `ct_*` element schema; Oxygen 6 is a ground-up
  rewrite with a new schema not supported here. Native Oxygen 6 support is
  planned for 4.3.
- **`get_fallback()` bug fix.** PHP converter was referencing
  `$this->current_id` which is not declared on the class (the property is
  `$this->id_counter`). Every fallback call would emit a PHP notice and
  produce `id => 1`.

### Avada / WPBakery / Beaver / Bootstrap

- No breakage detected in any of the four. Shortcode/element vocabularies have
  been stable across the audited version windows.
- Bootstrap file header doc-drift corrected (`5.3.3` → `5.3.x`).

---

## Test matrix

Existing pair tests are unaffected. The framework matrix grows from
**9 × 8 = 72** to **11 × 10 = 110** translation pairs. New fixtures:

- `tests/fixtures/kadence/simple-page.html`
- `tests/fixtures/thrive/simple-page.html`

---

## Deferred to 4.3

- **Elementor 4.x Atomic Editor** native parser/converter (currently
  passthrough only).
- **DIVI 5 block-based engine** native parser/converter (currently passthrough
  only).
- **Oxygen 6** schema research and new converter path (4.2 stays calibrated to
  Oxygen 4.x `ct_*` schema).
- **Bricks PHP converter** structural fix: nest `children` as flat id-string
  references instead of nested element objects (the Python converter already
  does this correctly).
- Full feature parity audit per converter — 4.2 covers breaking-change fixes
  only.

---

## Files added

```
translation-bridge/converters/class-kadence-converter.php
translation-bridge/converters/class-thrive-converter.php
translation-bridge/parsers/class-kadence-parser.php
translation-bridge/parsers/class-thrive-parser.php
src/translation_bridge/converters/kadence.py
src/translation_bridge/converters/thrive.py
tests/fixtures/kadence/simple-page.html
tests/fixtures/thrive/simple-page.html
RELEASE_NOTES_V4.2.0.md
```

## Files modified

```
pyproject.toml                                                   (version bump)
functions.php                                                    (DEVTB_THEME_VERSION)
tests/bootstrap.php                                              (DEVTB_VERSION)
style.css                                                        (theme header)
README.md                                                        (badges + framework list)
translation-bridge/core/interface-converter.php                  (new contract method)
translation-bridge/core/class-converter-factory.php              (Kadence + Thrive registration)
translation-bridge/core/class-parser-factory.php                 (Kadence + Thrive registration)
translation-bridge/converters/class-*-converter.php  (9 files)   (TARGET_CMS_VERSION + audit patches)
translation-bridge/parsers/class-elementor-parser.php            (is_atomic_v4_payload)
translation-bridge/parsers/class-divi-parser.php                 (is_divi5_payload)
src/translation_bridge/converters/*.py (9 files)                 (TARGET_CMS_VERSION + audit patches)
src/translation_bridge/parsers/elementor.py                      (is_atomic_v4_payload)
tests/Unit/FrameworkConversionsTest.php                          (matrix expansion to 110 pairs)
```

## Verification

`composer test` → 124 / 124 pass on `FrameworkConversionsTest` (full 110-pair
translation matrix + 14 supporting tests). The remaining 41 errors and 3
failures in the broader test suite are pre-existing 4.1.0 issues
(class-autoload mismatches and unrelated subsystem tests) that 4.2.0 does not
regress or change.
