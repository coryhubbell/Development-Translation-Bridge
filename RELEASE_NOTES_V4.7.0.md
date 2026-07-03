# Translation Bridge 4.7.0 — Release Notes

**Release date:** 2026-07-03
**Theme:** JSON source parsers for the lossless transform path

v4.7.0 ships the first item on the 4.7+ roadmap — the biggest fidelity win
available: **Bricks Builder, classic Oxygen, and Elementor 4 Atomic content
can now be *sources* on the 100%-metadata `transform` path.** Previously
only Elementor JSON could; these JSON-native formats were forced through the
~42%-fidelity HTML-intermediate path when used as sources.

```bash
./devtb transform bricks    gutenberg page.json
./devtb transform oxygen    gutenberg page.json
./devtb transform elementor4 bootstrap page.json
```

---

## The three new source parsers

Each parser normalizes its framework's native JSON into the universal
element shape the Python converters consume — built entirely on schemas
verified in earlier releases, nothing guessed:

### `BricksParser`

Reads the real Bricks 2.x flat page format (string `parent` ids, children
id lists — verified in v4.3.0), `{"content": [...]}` page exports, and the
legacy nested-children shape. Maps 30+ Bricks element names onto universal
widget types with Elementor-style setting keys (heading text/tag, button
link + newTab, image url/alt, testimonials, ...).

### `OxygenParser` (classic 4.x)

Mirrors the v4.6.0 PHP hardening: all four storage shapes parse — the
nested `ct_builder_json` root tree, the wrapper, the flat `ct_parent` list,
and `ct_builder_shortcodes` strings. Design props from `options.original`
carry through with unitless→px normalization, and responsive
`options.media.<breakpoint>.original` overrides canonicalize into the
shared responsive model (tablet, phone-portrait).

### `Elementor4Parser` (Atomic Editor)

Consumes the typed-prop schema verified against the open-source elementor
repo in v4.4.0: `{"$$type", "value"}` envelopes unwrap recursively
(`html-v3` content, `link.destination`/`isTargetBlank`, nested
`image.src → {id, url, alt}`), and style-definition variants canonicalize
per breakpoint/state (`mobile` ↔ `phone`) into the shared responsive model.
Accepts template envelopes (`{"content": [...], "version": "0.4"}`), bare
node lists, and single nodes.

## Wiring

- **CLI**: `get_parser_for_framework` resolves `bricks`, `oxygen`, and
  `elementor4` (plus `elementor-4` / `elementor-atomic` aliases) — the
  previously hardcoded elementor-only branch was the blocker that kept every
  other source off the transform path.
- **Registry**: five new transform pairs — `bricks→bootstrap`,
  `oxygen→gutenberg`, `oxygen→bootstrap`, `elementor4→gutenberg`,
  `elementor4→bootstrap` — joining the pre-existing `bricks→gutenberg`.
- **`parsers/universal.py`**: shared `UniversalElement`/`UniversalDocument`
  primitives (with the `to_dict`/`analyze`/`extract_content` surface the CLI
  expects), so the next source parser is a much smaller diff.

## Test coverage

16 new tests (`tests/python/test_source_parsers.py`): native-format
fixtures mirroring the verified schemas, parse assertions per framework,
responsive canonicalization checks, end-to-end transforms through the
registry, and registry/CLI wiring guards. The CLI path was also verified
manually end to end (`devtb transform bricks gutenberg` → correct block
markup, "100% metadata preservation" reported).

| | v4.6.0 | v4.7.0 |
|---|---|---|
| Python tests | 140 | **156** |
| PHP tests | 328 / 5,627 assertions | **328 / 5,627 assertions** |
| Failures / errors | 0 / 0 | **0 / 0** |
| Gutenberg e2e smoke | pass | **pass** |

## Compatibility

- Purely additive: no existing transform, parser, or converter behavior
  changed. The PHP engine is untouched.
- PHP 8.1+ / Python 3.9+ floors unchanged.

## What's next (from the roadmap)

E2e fidelity smoke gates for more targets, responsive canonicalization for
Elementor v3 / Bricks breakpoints, and Python parsers for the remaining
frameworks — the stepping stones toward the speculative 5.x engine
consolidation.
