# Translation Bridge 4.11.0 — Release Notes

**Release date:** 2026-07-03
**Theme:** Python source parsers final tranche — all 14 frameworks

v4.11.0 closes the last item on the 4.7+ roadmap: with seven new parsers
(DIVI 4, WPBakery, Avada, Kadence, Beaver Builder, Thrive, Bootstrap),
**every one of the 14 supported frameworks now parses natively in Python** —
JSON, block markup, shortcodes, and HTML. The parser half of the eventual
5.x engine consolidation is complete.

```bash
./devtb transform divi      gutenberg page.txt
./devtb transform wpbakery  bootstrap page.txt
./devtb transform bootstrap gutenberg page.html   # output re-enters the loop
```

---

## The seven new parsers

| Parser | Format | Foundation |
|---|---|---|
| `DiviParser` | `[et_pb_*]` shortcodes (DIVI 5 markup routes to `Divi5Parser`) | shared shortcode tokenizer |
| `WPBakeryParser` | `[vc_*]` — incl. the `url:...|target:...` link format and base64 `vc_raw_html` | shared shortcode tokenizer |
| `AvadaParser` | `[fusion_*]` (Container > Row > Column hierarchy) | shared shortcode tokenizer |
| `KadenceParser` | `kadence/*` blocks with `core/*` fallthrough | extends `GutenbergParser` |
| `BeaverParser` | flat node registry (parent ids, position ordering) | — |
| `ThriveParser` | TCB HTML (class-driven: `tcb-flex-*`, `tcb-button-block`, ...) | shared HTML walker |
| `BootstrapParser` | Bootstrap 5 HTML — the project's own output format re-enters the lossless path | shared HTML walker |

## Shared infrastructure

- **`parsers/shortcodes.py`** — a nested shortcode tokenizer with attribute
  parsing and closer look-ahead: self-closing leaves without `[/tag]`
  closers (common in WPBakery/DIVI exports) no longer swallow their
  siblings — a trap caught during development by the smoke checks.
- **`parsers/htmlbase.py`** — a stdlib-HTMLParser DOM-lite walker with
  overridable class-hint rules (headings, paragraphs, buttons, images,
  lists, blockquotes → universal widgets; structural tags → containers).

## Wiring

13 new transform pairs and CLI resolution for all seven sources. Verified
against real artifacts: the committed DIVI kitchen-sink fixture and the
repository's actual Bootstrap hero example both parse and transform end
to end.

## Test coverage

| | v4.10.0 | v4.11.0 |
|---|---|---|
| Python tests | 175 | **185** |
| Python-native source parsers | 7 | **14 (all)** |
| PHP tests | 332 / 5,640 assertions | **332 / 5,640 assertions** |
| Failures / errors | 0 / 0 | **0 / 0** |
| E2e smoke gates | 3 × 2 engines — pass | **3 × 2 engines — pass** |

## Compatibility

- Purely additive: no existing behavior changes. PHP engine untouched.
- PHP 8.1+ / Python 3.9+ floors unchanged.

## Roadmap position

**The 4.7+ roadmap is complete.** All four items shipped: JSON source
parsers (v4.7.0), e2e smoke gates (v4.8.0), responsive canonicalization
(v4.9.0), and Python parsers for every framework (v4.10.0 + this release).
What remains is the speculative 5.x engine consolidation — both halves of
which now exist.
