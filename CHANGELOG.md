# Changelog

All notable changes to DevelopmentTranslation Bridge are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
Detailed notes for major releases live in `RELEASE_NOTES_V*.md` and on
[GitHub Releases](https://github.com/coryhubbell/Development-Translation-Bridge/releases).

## [Unreleased]

### Added

- **RFC 5.0 Phase 2 (complete)** — `translation_bridge.interchange`, the
  Python mirror of `DEVTB_Component::to_universal()`: legacy
  component-shaped dicts (`type`/`attributes`/`content`/`children`)
  translate to canonical universal elements in one shared, spec-aligned
  module. A new conformance test asserts the two engines translate the
  component shape identically on real parsed fixtures.
- Component round-trip vocabulary completed in both engines: `icon_list`,
  `wp_gallery`, `selected_icon`, `alert_title`/`alert_description`, and
  call-to-action links now survive universal ⇄ component conversion.
- Gutenberg converter accepts the schema-canonical `nav` widgetType
  (alias of `nav-menu`).

### Changed

- The Python Gutenberg converter's ad-hoc component adapter
  (`_convert_component` attribute-translation layer) is replaced by
  delegation to `translation_bridge.interchange`; structural components
  (`row`/`column`) now convert to proper `core/columns`/`core/column`
  blocks instead of a flat group.

## [4.12.0] — 2026-07-03

RFC 5.0 opens: the canonical schema, dual-engine conformance, and universal
interchange in the PHP engine. Full notes:
[RELEASE_NOTES_V4.12.0.md](RELEASE_NOTES_V4.12.0.md).

### Added
- **RFC 5.0 Phase 2 (core) — universal interchange in the PHP engine**:
  - `DEVTB_Universal` (core): `components_to_document()` /
    `document_to_components()` — the bidirectional bridge between
    `DEVTB_Component` trees and the canonical universal document.
  - Translator entry points `parse_to_universal()` and
    `translate_universal()`.
  - REST `/translate` accepts `universal` as source (send a universal
    document, get target output) or target (get the parsed universal
    document).
  - Cross-engine interchange conformance-tested both directions: a
    Python-parsed document converts in the PHP engine, and a PHP-parsed
    document converts in the Python engine (`test_conformance.py`, now 11
    tests; new `UniversalInterchangeTest`, 6 PHP tests).
- **RFC 5.0 Phase 1 — engine-consolidation groundwork** (opens the 5.x
  chapter):
  - `docs/RFC-5.0-engine-consolidation.md` — the phased consolidation plan
    (spec + conformance → PHP interchange adoption → translate-path
    deprecation → 5.0).
  - `schema/universal-element.schema.json` — the canonical universal
    element document, normatively specified (element shape, widgetType
    vocabulary, Elementor-style settings keys, the v4.5.0 responsive
    model).
  - `DEVTB_Component::to_universal()` — the PHP engine emits the canonical
    shape (structural mapping, settings vocabulary translation, responsive
    metadata carry-through).
  - Dual-engine conformance suite (`tests/python/test_conformance.py`,
    9 tests): the Elementor kitchen-sink, DIVI kitchen-sink, and real
    Breakdance export fixtures are parsed by BOTH engines; outputs must be
    schema-valid and content-equivalent. Runs in CI with the Python suite.

## [4.11.0] — 2026-07-03

Python source parsers final tranche — all 14 frameworks parse natively.
Full notes: [RELEASE_NOTES_V4.11.0.md](RELEASE_NOTES_V4.11.0.md).

### Added
- **Python source parsers, final tranche: DIVI 4, WPBakery, Avada, Kadence,
  Beaver Builder, Thrive, and Bootstrap** — closing roadmap 4.7+ item 4.
  **All 14 frameworks now parse natively in Python**, completing the parser
  half of the eventual 5.x engine consolidation.
  - Shared `parsers/shortcodes.py` tokenizer (nested shortcodes, attr
    parsing, closer look-ahead for self-closing leaves) powers the
    `DiviParser` ([et_pb_*]), `WPBakeryParser` ([vc_*] incl. the
    `url:...|target:...` link format and base64 `vc_raw_html`), and
    `AvadaParser` ([fusion_*]).
  - Shared `parsers/htmlbase.py` (stdlib HTMLParser → universal walker)
    powers the `BootstrapParser` (Bootstrap output re-enters the lossless
    path) and `ThriveParser` (TCB class-driven detection).
  - `KadenceParser` extends the Gutenberg parser: kadence/* blocks
    (rowlayout, advancedheading, advancedbtn, infobox, ...) with core/*
    fallthrough.
  - `BeaverParser` reads the flat node registry (parent ids, position
    ordering).
  - 13 new transform pairs and CLI resolution for all seven; the DIVI
    kitchen-sink fixture and the real Bootstrap hero example parse and
    transform end to end.
  - 10 new tests (`test_source_parsers.py`, now 40; Python suite 185).

## [4.10.0] — 2026-07-03

Python source parsers tranche 2 — all JSON/block-markup formats now parse
natively in Python. Full notes:
[RELEASE_NOTES_V4.10.0.md](RELEASE_NOTES_V4.10.0.md).

### Added
- **Python source parsers, tranche 2: Oxygen 6, DIVI 5, and Gutenberg**
  (roadmap 4.7+ item 4, in progress) — seven frameworks now parse natively
  in Python, covering every JSON/block-markup format:
  - `Oxygen6Parser` — the verified Breakdance node shape (`data`-nested
    type/properties, `tree.root` envelope, element-copy envelope, legacy
    proxy fallback); design `breakpoint_*` leaves canonicalize into the
    responsive model. Parses the committed real Breakdance fixture
    end to end.
  - `Divi5Parser` — `wp:divi/*` block markup per the verified format
    (top-level `content` group, unicode-escaped HTML, `module.content`
    legacy fallback); responsive wrappers (tablet/phone/hover)
    canonicalize.
  - `GutenbergParser` — WordPress core block markup as a lossless SOURCE;
    unknown blocks preserve verbatim as `html` widgets.
  - Shared `parsers/blocks.py` block-comment tokenizer (stack-based
    nesting, mirrors WP `parse_blocks()`).
  - Seven new transform pairs (`oxygen6`/`divi5` → gutenberg/bootstrap;
    `gutenberg` → bootstrap/elementor/bricks) and CLI resolution.
  - 14 new tests (`test_source_parsers.py`, now 30).

## [4.9.0] — 2026-07-03

Responsive canonicalization for Elementor v3 and Bricks. Full notes:
[RELEASE_NOTES_V4.9.0.md](RELEASE_NOTES_V4.9.0.md).

### Added
- **Responsive canonicalization for Elementor v3 and Bricks** (roadmap 4.7+
  item 3): both frameworks now feed the canonical responsive model
  (desktop/tablet/phone × default/hover), completing coverage across all six
  responsive-capable paths.
  - Elementor v3: `_tablet`/`_mobile`/`_hover` setting suffixes canonicalize
    on parse (PHP + Python parsers) and re-emit on convert (PHP + Python
    converters).
  - Bricks: `:tablet_portrait`/`:mobile_portrait` setting-key suffixes
    canonicalize on parse and re-emit on convert (both engines);
    `mobile_landscape` passes through verbatim.
  - Cross-framework transfer works both directions (e.g. Elementor tablet
    overrides → Bricks `:tablet_portrait` keys), covered by 4 new PHP and 5
    new Python round-trip tests.

## [4.8.0] — 2026-07-03

E2e fidelity smoke gates + the seven content drops they caught. Full notes:
[RELEASE_NOTES_V4.8.0.md](RELEASE_NOTES_V4.8.0.md).

### Added
- **Two new e2e fidelity smoke gates** (roadmap 4.7+ item 2), each running
  through both engines as CI gates on every push/PR alongside the original
  Elementor → Gutenberg gate:
  - **Elementor → Bricks** (`tests/smoke_bricks_e2e.py`): flat 2.x format
    integrity (string ids, resolvable parent/child linkage, no nested child
    objects) plus content survival for every content-bearing widget category.
  - **DIVI → Gutenberg** (`tests/smoke_divi_e2e.py`): new DIVI kitchen-sink
    fixture parsed once in PHP, then converted by BOTH Gutenberg converters
    (the Python engine consumes the exported universal components); asserts
    content survival, balanced delimiters, and no empty-paragraph collapses.
  - Shared harness helpers extracted to `tests/smoke_lib.py`;
    `make e2e-smoke` runs all three gates (wired into `make verify`).

### Fixed
- **Seven real content drops caught by the new gates on their first run:**
  - Python Bricks converter: testimonial bodies/names, CTA titles, icon-box,
    price-table, alert, blockquote, icon-list, gallery, and social-icons
    settings were silently dropped (no widget branches existed).
  - PHP Bricks converter: gallery images emitted as a JSON-string blob
    instead of the real Bricks `images` array.
  - Python Gutenberg converter: universal container components (from PHP
    parsers) hit the marker path and dropped ALL their children; DIVI-style
    attribute names (`label`, `image_url`, `heading`, `author`) now translate
    to the widget builders' expected keys.
  - PHP Gutenberg converter: button text from `label` attributes
    (DIVI/WPBakery sources), single-panel toggle headings/bodies, and DIVI
    testimonial author/job/company citations were dropped.

## [4.7.0] — 2026-07-03

JSON source parsers for the lossless transform path. Full notes:
[RELEASE_NOTES_V4.7.0.md](RELEASE_NOTES_V4.7.0.md).

### Added
- **JSON source parsers for the lossless `transform` path** (roadmap 4.7+
  item 1): Bricks Builder, classic Oxygen, and Elementor 4 Atomic content now
  parse into the universal element shape and ride the 100%-metadata Python
  engine as sources.
  - `BricksParser` — real 2.x flat page format (string `parent` ids,
    children id lists), `{"content": [...]}` exports, and the legacy nested
    shape.
  - `OxygenParser` — all four classic storage shapes (root tree, wrapper,
    flat list, shortcodes) with unit normalization and `options.media`
    responsive canonicalization, mirroring the v4.6.0 PHP hardening.
  - `Elementor4Parser` — typed-prop unwrapping (`$$type` envelopes,
    `html-v3` content, `link.destination`), style-variant canonicalization
    into the shared responsive model.
  - New transform registrations: `bricks→bootstrap`, `oxygen→gutenberg`,
    `oxygen→bootstrap`, `elementor4→gutenberg`, `elementor4→bootstrap`
    (joining the existing `bricks→gutenberg`).
  - CLI: `devtb transform bricks|oxygen|elementor4 <target> file.json` now
    works end to end; shared `UniversalDocument` primitives added for future
    source parsers.
  - 16 new parser/wiring tests (`tests/python/test_source_parsers.py`).

## [4.6.0] — 2026-07-03

Classic Oxygen hardening. Full notes:
[RELEASE_NOTES_V4.6.0.md](RELEASE_NOTES_V4.6.0.md).

### Fixed
- **Classic Oxygen (4.x) support fully hardened** (audit-driven):
  - Parser now accepts every real storage shape: the nested `ct_builder_json`
    root tree, the `ct_builder_json` wrapper, the flat `ct_parent` list, and
    `ct_builder_shortcodes` strings (previously only the flat list parsed —
    the committed fixture itself was unreadable).
  - Element vocabulary corrected to classic Oxygen's real names: `ct_link`,
    `ct_new_columns`/`ct_column`, `oxy_rich_text`, `ct_svg_icon`, `oxy_tabs`/
    `oxy_tab`/`oxy_tab_content`, `oxy_toggle`, `oxy_testimonial_box`,
    `oxy_pricing_box`, `oxy_progress_bar`, `oxy_icon_box`, `oxy_map`,
    `oxy_nav_menu`, `oxy_login_form`, `oxy_search_form`, `oxy_share_box`,
    `oxy_superbox`, `oxy_shortcode` — fabricated names (`ct_link_text`,
    `ct_tabs`, `ct_google_map`, `ct_testimonial`, ...) are still parsed as
    aliases but never emitted. PHP and Python converters now emit identical
    vocabulary and the same root-tree output shape (previously three
    mutually incompatible shapes).
  - Styles: `options.original` passes through in full (the old 33-prop
    allow-list silently dropped `gap`, `border` shorthand, `box-shadow`,
    positioning, etc.) with unit normalization both ways (Oxygen unitless ↔
    CSS px).
  - Responsive: `options.media.<breakpoint>.original` overrides now
    round-trip via the canonical responsive model (tablet, phone-portrait).
  - Deterministic selectors (was `time()`-based — output is now reproducible).
  - Content extraction for testimonials (quote/author/title), icons, image
    attachments, and reusable-part references; containers no longer carry
    `ct_content`.
  - New `OxygenClassicHardeningTest` (9 tests) including a full fixture
    round trip; the 182-pair matrix now structurally validates every
    `*_to_oxygen` conversion.

### Changed
- The `oxygen-6` path intentionally tracks the verified Breakdance-derived
  schema; the README no longer solicits Oxygen 6-specific export fixtures.

## [4.5.0] — 2026-07-03

Responsive breakpoint round-tripping. Full notes:
[RELEASE_NOTES_V4.5.0.md](RELEASE_NOTES_V4.5.0.md).

### Added
- **Responsive breakpoint round-tripping** for `divi-5`, `elementor-4`, and
  `oxygen-6` (closes the remaining v4.3.x roadmap item). A canonical
  responsive model (breakpoints `desktop`/`tablet`/`phone`, states
  `default`/`hover`; `DEVTB_Responsive_Helper` in PHP,
  `translation_bridge.responsive` in Python) carries breakpoint data through
  the universal component metadata:
  - DIVI 5: content-field wrappers (`{"desktop": {"value", "hover"},
    "tablet": ..., "phone": ...}`) canonicalize on parse and re-emit in full.
  - Elementor 4: style-definition variants canonicalize per
    breakpoint/state (`mobile` ↔ `phone`) and re-emit as one variant each.
  - Oxygen 6: design-tree `breakpoint_*` leaves flatten to canonical props
    and re-nest on emit.
  - Responsive styling also transfers **across** frameworks (e.g. Oxygen 6
    design breakpoints → Elementor 4 variants), covered by 8 PHP + 6 Python
    round-trip tests.

## [4.4.0] — 2026-07-02

Schema verification + release-engineering modernization. Full notes:
[RELEASE_NOTES_V4.4.0.md](RELEASE_NOTES_V4.4.0.md).

### Fixed
- **Proxy schemas verified against real evidence** (`oxygen-6`, `divi-5`,
  `elementor-4` — the v4.3.0 roadmap item):
  - Elementor 4 settings now use the real typed-prop system verified against
    the open-source elementor repo: `$$type` envelopes, `html-v3` content
    props, the `paragraph` settings key, `link.destination`/`isTargetBlank`,
    nested `image.src` shape, `Style_Definition` variants, and only real
    atomic element types (`e-svg`, `e-youtube`, `e-divider`, ... — no more
    `e-video`/`e-icon`/`e-list`).
  - DIVI 5 attrs now carry content in the top-level `content` group (not
    `module.content`) with WP-style unicode-escaped HTML, per the Divi 5
    block-format docs.
  - Oxygen 6 nodes now match a real Breakdance element export: integer ids,
    `data`-nested type/properties, `_parentId` back-references, `tree.root`
    envelope, `content.content` field grouping, plural `tags` heading key,
    and real element names (`CodeBlock`, `TextLink`, `PricingTable`,
    `ProgressBar`).
  - Parsers accept both the real shapes and the legacy proxy shapes for
    back-compat; `tests/Unit/ProxySchemaVerificationTest.php` pins the real
    formats, including parsing a committed real Breakdance export fixture.

### Added
- Dependabot automation across five ecosystems: Composer, npm (`admin/`), and
  pip weekly; GitHub Actions and Docker Compose monthly.
- `scripts/build-release-package.sh` — reproducible WordPress theme zip
  packaging, used by both the release workflow and a new CI smoke job.
- Two new CI jobs: admin build (ESLint + `tsc --noEmit` + Vite production
  build on Node 20.19.0 / 22.13.0 / 24) and release-package smoke.
- `make verify` — full local release gate mirroring CI (composer validate /
  install / audit, PHP + Python tests, Gutenberg e2e smoke, admin lint /
  typecheck / build / audit, release-package smoke, whitespace check).
- `LICENSE` file (GPL-2.0-or-later) committed to the repository root.
- `.python-version` pinning local verification to Python 3.11.
- Additional Gutenberg widget-coverage tests (18 total), Python project
  alignment checks, and the new `ProxySchemaVerificationTest` (Python suite
  now 133 tests; PHP suite 311 tests / 4,818 assertions).
- README: live CI badge, Mermaid architecture + engine-selection diagrams,
  client-focused framing, Visual Interface screenshot, CI/Dependabot/release
  packaging documentation.

### Changed
- PHP test matrix extended to 8.1–8.5; release workflow granted
  `contents: write` so tag pushes publish releases automatically.
- Dependency stack modernized (Vite 8, typescript-eslint 8.62, React Query
  5.101, Composer dependency refresh).
- Local dev containers: MySQL image 8.4.10 → 9.7.1.

## [4.3.4] — 2026-05-20

Elementor → Gutenberg widget coverage hotfix. Full notes:
[RELEASE_NOTES_V4.3.4.md](RELEASE_NOTES_V4.3.4.md).

### Fixed
- ~70 of the 90+ universal widget types no longer collapse silently onto
  empty `core/paragraph` blocks. Compound widgets (`tabs`, `accordion`,
  `card`, `cta`, `counter`, `testimonial`, `pricing-table`, `alert`) expand
  into native block groups with a `devtb-<type>-converted` className.
- Widgets with no native Gutenberg equivalent (`form`, `slider`, `countdown`,
  `portfolio`, `toc`, `map`, `progress`, `rating`, unknown types) are
  preserved as `core/html` with a `data-devtb-source` annotation — no silent
  data loss.
- Type-map gaps closed for `social-icons`, `nav`, `blockquote`, `icon`.
- Settings denormalization (typography, color, spacing, border, className,
  anchor) restored on the Python side.

### Added
- Four new registered transforms: `elementor_to_gutenberg`,
  `html_to_gutenberg`, `divi_to_gutenberg`, `bricks_to_gutenberg`.
- End-to-end smoke harness (`tests/smoke_gutenberg_e2e.py`): kitchen-sink
  Elementor fixture through both engines, wired into CI as a gate on every
  push and PR.

## [4.3.3] — 2026-05-19

### Changed
- `functions.php` admin pages (admin home, Frameworks matrix, Settings
  select, System Status, Framework Details) are now factory-driven via
  `DEVTB_Converter_Factory::get_framework_info()` — adding a 15th framework
  requires only a factory update.

## [4.3.2] — 2026-05-19

### Fixed
- User-facing copy synced to 14 frameworks / 182 pairs: `style.css`
  framework list, ASCII banner, admin help text, CLI help text.

## [4.3.1] — 2026-05-19

Production-readiness patch.

### Fixed
- CLI translation fatal: the inline autoloader mangled namespaced class
  names — replaced with a shared autoloader used by CLI, PHPUnit, and
  WordPress.
- Framework matrix consistency: REST API, CLI, file handler, config, admin
  TypeScript, and Monaco language map all derive from the converter factory;
  stale `claude` pseudo-framework purged.
- Test suite: 41 errors + 3 failures → 0 / 0 (284 tests, 4,133 assertions);
  PHP 8.5 deprecations → 0.

### Changed
- PHP floor raised to 8.1+ to match the tested runtime.

### Security
- CVE-2026-24765 (unsafe deserialization in PHPT coverage) cleared via
  PHPUnit 9.6.34.

## [4.3.0] — 2026-05-19

### Added
- Three new frameworks with native parser + converter pairs: `divi-5`
  (block markup), `elementor-4` (Atomic Editor), `oxygen-6`
  (Breakdance-derived JSON tree). Matrix: 11 → 14 frameworks,
  110 → 182 pairs.
- Automatic routing: legacy DIVI and Elementor parsers detect successor
  formats and route to the new parsers.

### Fixed
- Bricks PHP converter emits the flat 2.x page format (string `parent` ids,
  child id arrays) matching real Bricks output.

## [4.2.0] — 2026-05-18

Kadence + Thrive converters and CMS re-association. Full notes:
[RELEASE_NOTES_V4.2.0.md](RELEASE_NOTES_V4.2.0.md).

### Added
- `kadence` converter targeting Kadence Blocks 3.7.2
  (`kadence/rowlayout → kadence/column → leaf`, `core/*` fallthrough).
- `thrive` converter targeting Thrive Architect 10.8.10 (TCB HTML with
  `data-css` tokens and `tve_custom_style`).

### Fixed
- Correctness audit against current CMS versions: canonical Gutenberg
  serialization (heading/button/separator classes, `align` → `textAlign`,
  `core/list-item` innerBlocks, unit-qualified column widths) and Elementor
  `cta` mapping moved off the Pro-only `call-to-action` widget.

## [4.1.0] — 2026-01-17

### Added
- Eight Python framework converters completing the v4 converter suite.
- Site-level conversion: `ElementorSiteParser`, styles/token extraction,
  and template conversion for whole-kit migrations.

## [4.0.0] — 2025-Q4

### Added
- JSON-native `transform` engine (Python v4) with Zone Theory mapping and
  100% metadata preservation, alongside the existing PHP v3
  HTML-intermediate `translate` path.

## [3.0.0] — 2025-01

### Added
- Translation Bridge™: first multi-framework release — Bootstrap 5.3.3,
  DIVI, Elementor, Avada Fusion, and Bricks (20 translation pairs).
  Full notes: [RELEASE_NOTES_V3.0.0.md](RELEASE_NOTES_V3.0.0.md).

[Unreleased]: https://github.com/coryhubbell/Development-Translation-Bridge/compare/v4.12.0...HEAD
[4.12.0]: https://github.com/coryhubbell/Development-Translation-Bridge/compare/v4.11.0...v4.12.0
[4.11.0]: https://github.com/coryhubbell/Development-Translation-Bridge/compare/v4.10.0...v4.11.0
[4.10.0]: https://github.com/coryhubbell/Development-Translation-Bridge/compare/v4.9.0...v4.10.0
[4.9.0]: https://github.com/coryhubbell/Development-Translation-Bridge/compare/v4.8.0...v4.9.0
[4.8.0]: https://github.com/coryhubbell/Development-Translation-Bridge/compare/v4.7.0...v4.8.0
[4.7.0]: https://github.com/coryhubbell/Development-Translation-Bridge/compare/v4.6.0...v4.7.0
[4.6.0]: https://github.com/coryhubbell/Development-Translation-Bridge/compare/v4.5.0...v4.6.0
[4.5.0]: https://github.com/coryhubbell/Development-Translation-Bridge/compare/v4.4.0...v4.5.0
[4.4.0]: https://github.com/coryhubbell/Development-Translation-Bridge/compare/v4.3.4...v4.4.0
[4.3.4]: https://github.com/coryhubbell/Development-Translation-Bridge/compare/v4.3.3...v4.3.4
[4.3.3]: https://github.com/coryhubbell/Development-Translation-Bridge/compare/v4.3.2...v4.3.3
[4.3.2]: https://github.com/coryhubbell/Development-Translation-Bridge/compare/v4.3.1...v4.3.2
[4.3.1]: https://github.com/coryhubbell/Development-Translation-Bridge/compare/v4.3.0...v4.3.1
[4.3.0]: https://github.com/coryhubbell/Development-Translation-Bridge/compare/v4.2.0...v4.3.0
[4.2.0]: https://github.com/coryhubbell/Development-Translation-Bridge/compare/v4.1.0...v4.2.0
[4.1.0]: https://github.com/coryhubbell/Development-Translation-Bridge/compare/v4.0.0...v4.1.0
[4.0.0]: https://github.com/coryhubbell/Development-Translation-Bridge/releases/tag/v4.0.0
[3.0.0]: https://github.com/coryhubbell/Development-Translation-Bridge/blob/main/RELEASE_NOTES_V3.0.0.md
