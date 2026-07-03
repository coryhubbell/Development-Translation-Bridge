# Changelog

All notable changes to DevelopmentTranslation Bridge are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
Detailed notes for major releases live in `RELEASE_NOTES_V*.md` and on
[GitHub Releases](https://github.com/coryhubbell/Development-Translation-Bridge/releases).

## [Unreleased]

Nothing yet.

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

[Unreleased]: https://github.com/coryhubbell/Development-Translation-Bridge/compare/v4.7.0...HEAD
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
