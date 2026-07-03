# Translation Bridge 4.4.0 — Release Notes

**Release date:** 2026-07-02
**Theme:** Real-format schema verification + release-engineering modernization

v4.4.0 closes the last open item from the v4.3.0 roadmap — verifying the
`divi-5`, `elementor-4`, and `oxygen-6` proxy schemas against real evidence —
and lands the release-engineering work that accumulated on main since v4.3.4
(Dependabot, reproducible packaging, an expanded CI pipeline, and `make
verify`).

---

## Schema verification (the headline)

v4.3.0 shipped the three next-generation framework paths against published
documentation, with every assumption isolated to a single helper for later
correction. v4.4.0 makes those corrections against real evidence:

### Elementor 4 Atomic — verified against the open-source elementor repo

Source of truth: `modules/atomic-widgets` in
[elementor/elementor](https://github.com/elementor/elementor).

- **Typed-prop system.** Real exports wrap every stored setting in a
  `{"$$type": <key>, "value": ...}` envelope. Headings/paragraphs/buttons now
  emit `html-v3` props (nesting a `string` prop under `content`); scalars wrap
  as `string`/`number`/`boolean`.
- **Correct setting keys.** Paragraph content lives under `paragraph` (not
  `text`); links store `destination` + `isTargetBlank` (not `url`/`target`);
  images nest `src → {id, url, alt}` plus a `size` field.
- **Real element types only.** `e-video`, `e-icon`, and `e-list` do not exist
  in the atomic registry — emissions now use `e-youtube`,
  `e-self-hosted-video`, `e-svg`, and `e-divider`.
- **Real style structure.** Styles emit `Style_Definition` entries —
  `{id, type, label, variants: [{meta: {breakpoint, state}, props}]}` —
  referenced from settings via the `classes` prop.
- The parser unwraps typed props recursively and keeps plain-value fallbacks
  so legacy proxy output still parses.

### DIVI 5 — verified against the Divi 5 block-format docs

- **Top-level `content` group.** Module content lives at
  `attrs.content.innerContent` (etc.), not `attrs.module.content`; `module`
  holds meta/decoration. The responsive `{"desktop": {"value": ...}}` wrapper
  was verified correct as-shipped.
- **Unicode-escaped attrs.** HTML inside block-comment attributes now escapes
  `<`, `>`, `&`, `--`, and embedded quotes exactly like WP core's
  `serialize_block_attributes()`, so content can never break the comment
  delimiters. (Uses WP's own function when available.)
- The parser reads the real shape and keeps `module.content` as a fallback.

### Oxygen 6 — verified against a REAL Breakdance element export

A production Breakdance element export (Oxygen 6 shares ~80% of Breakdance's
codebase — exactly the proxy target) is now committed, scrubbed, at
`tests/fixtures/oxygen6/breakdance-element-export.json`. It corrected the
node shape wholesale:

- **Nodes:** integer ids, the element payload nested under `data`
  (`{"id": 102, "data": {"type": "EssentialElements\\Heading",
  "properties": {...}}, "children": [...], "_parentId": 101}`).
- **Envelope:** a `tree` object wrapping a `root` node, plus `_nextNodeId`.
- **Properties:** content fields nest under `content.content`; `design`,
  `meta` (`friendlyName`), and `settings` are sibling sections; heading tags
  use the plural `tags` key.
- **Element names:** `CodeBlock` (with `php_code`), `TextLink`,
  `PricingTable`, `ProgressBar` replace the invented `Code`/`Link`/`Pricing`/
  `Progress`.
- The `EssentialElements\` namespace was confirmed correct. The parser is
  namespace-agnostic and accepts the legacy flat proxy shape, real full-page
  envelopes, and element-copy envelopes.

### New test coverage

`tests/Unit/ProxySchemaVerificationTest.php` (9 tests / 47 assertions) pins
all of the above — including parsing the real Breakdance export end-to-end
and round-tripping it back out in the verified shape. The 182-pair conversion
matrix now uses real-shape sample inputs for all three frameworks.

**Remaining known gap:** an Oxygen 6-specific export (vs. Breakdance) would
close the final stretch of uncertainty. The element namespace is one constant;
contributions of real Oxygen 6 exports are welcome.

---

## Release engineering (since v4.3.4)

- **Dependabot** across five ecosystems: Composer, npm (`admin/`), pip
  (weekly); GitHub Actions, Docker Compose (monthly).
- **Reproducible packaging:** `scripts/build-release-package.sh` builds the
  WordPress theme zip; pushing a `v*` tag publishes the GitHub Release
  automatically (changelog included).
- **CI expanded to four jobs:** PHP 8.1–8.5 matrix, Python tests + Gutenberg
  e2e smoke, admin build on Node 20.19.0/22.13.0/24, and a release-package
  smoke on every push/PR.
- **`make verify`** mirrors the full release gate locally.
- **LICENSE** (GPL-2.0-or-later) committed; `.python-version` pins local
  verification to Python 3.11; dependency stack modernized (Vite 8,
  typescript-eslint 8.62, React Query 5.101).
- Docs overhaul: live CI badge, Mermaid architecture + engine-selection
  diagrams, Visual Interface screenshot, consolidated `CHANGELOG.md`,
  client-focused README framing.

---

## Test results

| | v4.3.4 | v4.4.0 |
|---|---|---|
| PHP tests | 302 / 4,250 assertions | **311 / 4,818 assertions** |
| Python tests | 125 | **133** |
| Failures / errors | 0 / 0 | **0 / 0** |
| Gutenberg e2e smoke | pass | **pass** |

## Compatibility

- Output format changes for `divi-5`, `elementor-4`, and `oxygen-6` targets
  (that is the point of the release). Parsers accept both the new real shapes
  and the old proxy shapes, so content produced by v4.3.x still translates.
- No changes to the other 11 framework paths, the REST API, or the CLI
  surface.
- PHP 8.1+ / Python 3.9+ floors unchanged.
