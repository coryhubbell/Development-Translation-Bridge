# Translation Bridge 4.10.0 — Release Notes

**Release date:** 2026-07-03
**Theme:** Python source parsers tranche 2 — every JSON/block-markup format

v4.10.0 advances roadmap item 4: with `Oxygen6Parser`, `Divi5Parser`, and
`GutenbergParser`, **seven frameworks now parse natively in Python** —
covering every JSON and block-markup format the project supports. Gutenberg
content itself becomes a lossless *source*:

```bash
./devtb transform gutenberg bricks    page.html
./devtb transform divi5     gutenberg page.html
./devtb transform oxygen6   bootstrap page.json
```

---

## The three new parsers

### `Oxygen6Parser`

Consumes the Breakdance-verified node shape (v4.4.0): integer ids, the
element payload nested under `data`, `_parentId` back-references, content
under `properties.content.content` (plural `tags` heading key). Accepts the
`tree.root` envelope, the element-copy envelope, bare nodes, and the legacy
proxy shape. Design `breakpoint_*` leaves canonicalize into the shared
responsive model. **Parses the committed real Breakdance export fixture end
to end.**

### `Divi5Parser`

Tokenizes `wp:divi/*` block markup per the verified format: top-level
`content` attribute group (with `module.content` legacy fallback),
unicode-escaped HTML handled transparently, and responsive wrappers —
tablet/phone breakpoints and hover states — canonicalizing via the new
parse-direction helpers.

### `GutenbergParser`

WordPress core block markup as a lossless source: headings, paragraphs,
buttons, images, quotes (→ testimonial with citation), lists, galleries,
video, separators/spacers, and code/html — with unknown blocks preserved
verbatim as `html` widgets rather than dropped. Core-namespace
normalization mirrors WP's own `parse_blocks()`.

## Shared infrastructure

- `parsers/blocks.py` — a stack-based block-comment tokenizer producing
  WP-`parse_blocks()`-shaped trees, reused by both markup parsers (and
  ready for Kadence in a future tranche).
- Parse-direction responsive helpers (`divi5_wrapper_to_canonical`,
  `oxygen6_design_to_canonical`) join the Python helper module, mirroring
  the PHP `DEVTB_Responsive_Helper`.
- Seven new transform pairs: `oxygen6`/`divi5` → gutenberg/bootstrap and
  `gutenberg` → bootstrap/elementor/bricks.

## Test coverage

| | v4.9.0 | v4.10.0 |
|---|---|---|
| Python tests | 161 | **175** |
| PHP tests | 332 / 5,640 assertions | **332 / 5,640 assertions** |
| Python-native source parsers | 4 | **7** |
| Failures / errors | 0 / 0 | **0 / 0** |
| E2e smoke gates | 3 × 2 engines — pass | **3 × 2 engines — pass** |

## Compatibility

- Purely additive: no existing parser, converter, or transform behavior
  changes. PHP engine untouched.
- PHP 8.1+ / Python 3.9+ floors unchanged.

## Roadmap position

Item 4 remains *in progress*: the shortcode/HTML formats (DIVI 4, WPBakery,
Avada, Kadence, Thrive, Beaver Builder, Bootstrap) are the remaining
tranche — after which the 5.x engine consolidation has both halves in place.
