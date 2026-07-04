# Translation Bridge 4.14.0 — Release Notes

**Release date:** 2026-07-04
**Theme:** RFC 5.0 Phase 3 — one conversion semantics, `translate` deprecated

v4.14.0 ships [RFC 5.0](docs/RFC-5.0-engine-consolidation.md) Phase 3: every
conversion — including the legacy `translate` path — now rides the lossless
parse → universal → convert pipeline, with fidelity reported per conversion.
Only Phase 4 (the 5.0.0 breaking release) remains.

---

## The translate path rides the universal document

- **`DEVTB_Translator::translate()`** no longer runs the v3 fuzzy
  mapping engine on the main path. Components normalize through the
  canonical universal document (`components_to_document` →
  `document_to_components`) before conversion — deterministic vocabulary
  translation instead of similarity-scored guessing. All 182 matrix pairs
  stay green.
- **The v3 mapping engine survives only as a content-extraction
  fallback**, gated by a content-survival check: if the universal round
  trip would lose a content string (text-normalized comparison — entities
  decoded, tags stripped, whitespace collapsed), the conversion falls
  back rather than dropping content.

## Fidelity metrics per conversion

- Translator stats now report `route` (`universal` / `mapping-fallback`)
  and `fidelity` (content strings preserved / total, ratio).
- Both CLIs print the metric on every conversion, e.g.
  `Fidelity: 5/6 content strings preserved (83.3%)`. The collector is
  content-key-aware, so style enums never count as content.

## `translate` is deprecated (removed in 5.0)

- The PHP CLI and the `devtb` wrapper print deprecation notices; the
  Python CLI accepts `translate` as an alias of `transform`.
- Unregistered Python pairs now convert through the **universal route**
  (any parsed document → any of the 14 converters) behind a runtime
  fidelity gate: under 50% content survival, the CLI falls back to content
  extraction honestly instead of emitting structurally empty output.

## Bugs fixed

- `devtb-php` never defined the `DEVTB_CLI` constant, so real CLI
  translations touching the responsive helper (e.g. Bricks targets)
  silently `exit`ed mid-conversion with no output and exit code 0.
- The PHP CLI statistics block read stat keys that never existed.
- `wp_strip_all_tags` stubbed for CLI mode.

## Test coverage

| | v4.13.0 | v4.14.0 |
|---|---|---|
| Python tests | 237 | **262** (incl. 25 translate-path) |
| PHP tests | 339 / 5,673 assertions | **344 / 5,691 assertions** |
| Failures / errors | 0 / 0 | **0 / 0** |
| E2e smoke gates | 3 × 2 engines — pass | **3 × 2 engines — pass** |

## Compatibility

- CLI and REST surfaces are preserved: same commands, same arguments,
  same endpoints. What changed is the internal route (now lossless) plus
  new advisory stats keys and deprecation notices.
- `devtb translate` behavior note: conversions that previously went
  through fuzzy mapping now produce canonical-vocabulary output; a
  content-survival gate guarantees no regression in content preservation.
- PHP 8.1+ / Python 3.9+ floors unchanged.

## Roadmap position

Phases 1–3 are complete. Phase 4 is the 5.0.0 breaking release: remove the
HTML-intermediate pipeline and the component-shaped public interchange
(`translate` alias retained for one minor). Optional pre-5.0 tranche:
harden the Python exotic-target converters (kadence, thrive, wpbakery,
avada as targets) to full cross-source universal-document parity — the
fidelity sweep now makes those gaps measurable.
