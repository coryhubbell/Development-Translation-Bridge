# Translation Bridge 4.6.0 — Release Notes

**Release date:** 2026-07-03
**Theme:** Classic Oxygen (4.x) hardening

v4.6.0 is an audit-driven overhaul of the classic Oxygen path. The audit found
the pre-4.6 implementation couldn't read its own fixture, emitted nine
element names that don't exist in classic Oxygen, and used three mutually
incompatible output shapes across the PHP parser, PHP converter, and Python
converter. All of that is fixed, with the stable, fully documented Oxygen 4.x
schema as the target.

---

## Parser: every real storage shape

`DEVTB_Oxygen_Parser` now accepts all four shapes classic Oxygen actually
uses:

| Shape | Example | Before |
|---|---|---|
| Nested root tree | `{"id":0,"name":"root","children":[...]}` (the real `ct_builder_json` post meta) | **unreadable** |
| Wrapper | `{"ct_builder_json": {...}}` | unreadable |
| Flat list | `[{"id":1,"name":"ct_section","options":{"ct_parent":0,...}}]` | supported |
| Shortcodes | `[ct_section ct_options='{...}']…` (`ct_builder_shortcodes`) | unreadable |

## Real element vocabulary

Both converters now emit only classic Oxygen's real element names:
`ct_link`, `ct_new_columns`/`ct_column`, `ct_svg_icon`, `oxy_rich_text`,
`oxy_tabs`/`oxy_tab`/`oxy_tab_content`, `oxy_toggle`, `oxy_testimonial_box`,
`oxy_pricing_box`, `oxy_progress_bar`, `oxy_icon_box`, `oxy_map`,
`oxy_nav_menu`, `oxy_login_form`, `oxy_search_form`, `oxy_share_box`,
`oxy_superbox`, `oxy_shortcode`, `ct_reusable`.

The fabricated names earlier releases emitted (`ct_link_text`, `ct_tabs`,
`ct_google_map`, `ct_testimonial`, `ct_pricing_box`, `ct_progress_bar`,
`ct_nav_menu`, `ct_icon`, `ct_gallery`, ...) still parse as aliases for
back-compat but are never emitted again.

## Unified output shape (PHP + Python)

Both engines emit the identical real `ct_builder_json` root-tree shape —
nested `children` plus redundant `ct_id`/`ct_parent` linkage in options,
exactly like real exports. Previously the PHP converter emitted a bare flat
array, the Python converter a wrapped nested tree with different option
keys, and neither matched what the PHP parser read best. A Python
id-mismatch bug (element `id` ≠ `options.ct_id`) is fixed.

## Style and responsive fidelity

- **Full `options.original` passthrough.** The old 33-property allow-list
  silently dropped `gap`, `border` shorthand, `box-shadow`, positioning, and
  more — properties the committed fixture itself uses.
- **Unit normalization both ways.** Classic Oxygen stores length values
  unitless (`"padding-top": "80"`); parsing appends `px` for valid CSS and
  emission strips it back — stable across round trips.
- **Responsive `options.media` round-tripping.** Per-breakpoint overrides
  (`tablet`, `phone-portrait`) canonicalize into the v4.5.0 responsive model
  and re-emit — previously all responsive data was dropped.
- **Deterministic output.** Selectors were `time()`-suffixed; output is now
  byte-reproducible.

## Content coverage

Testimonial fields (quote/author/title), icon ids, media-library attachment
references, and reusable-part ids now extract into the universal model
instead of surviving only as opaque metadata. Containers no longer carry
`ct_content`.

## Oxygen 6 positioning

The `oxygen-6` path intentionally tracks the verified Breakdance-derived
schema (~80% shared codebase, node shape verified against a real export in
v4.4.0). The README no longer solicits Oxygen 6-specific fixtures.

---

## Test coverage

- New `tests/Unit/OxygenClassicHardeningTest.php` — 9 tests: all four input
  shapes, a full fixture round trip, style passthrough + unit normalization,
  responsive media round trip, deterministic output, and real-vocabulary
  guards.
- The 182-pair conversion matrix now structurally validates every
  `*_to_oxygen` conversion (root tree, real names, `ct_id`/`ct_parent`
  linkage).

| | v4.5.0 | v4.6.0 |
|---|---|---|
| PHP tests | 319 / 4,861 assertions | **328 / 5,627 assertions** |
| Python tests | 139 | **140** |
| Failures / errors | 0 / 0 | **0 / 0** |
| Gutenberg e2e smoke | pass | **pass** |

## Compatibility

- **Oxygen target output shape changed** from a bare flat array to the real
  `ct_builder_json` root tree — consumers of the old shape should read
  `children` (the parser accepts both, so round trips through Translation
  Bridge are unaffected).
- Content produced by earlier releases (fabricated element names, flat
  arrays) still parses via aliases.
- No changes to the other 13 framework paths, the REST API, or the CLI
  surface. PHP 8.1+ / Python 3.9+ floors unchanged.
