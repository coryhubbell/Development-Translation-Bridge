# Translation Bridge 4.8.0 — Release Notes

**Release date:** 2026-07-03
**Theme:** E2e fidelity smoke gates — and the seven content drops they caught

v4.8.0 ships roadmap item 2: the kitchen-sink smoke-gate pattern that guarded
Elementor → Gutenberg (and caught two real bugs before v4.3.4 shipped) now
also guards **Elementor → Bricks** and **DIVI → Gutenberg**, through both
engines, on every push and PR. The gates proved themselves on their first
run: seven real content drops across all four converter surfaces, all fixed
in this release.

---

## The new gates

| Gate | Fixture | Asserts |
|---|---|---|
| **Elementor → Bricks** (`tests/smoke_bricks_e2e.py`) | Elementor kitchen-sink | Flat Bricks 2.x format integrity — string ids, resolvable parent/child linkage, no nested child objects — plus content survival for every content-bearing widget category |
| **DIVI → Gutenberg** (`tests/smoke_divi_e2e.py`) | New DIVI kitchen-sink (17 module categories) | Content survival, balanced block delimiters, no empty-paragraph collapses. The fixture parses once in PHP; BOTH Gutenberg converters consume it (the Python engine via exported universal components) |

Shared harness helpers live in `tests/smoke_lib.py` (the original gate was
refactored onto it, behavior unchanged). `make e2e-smoke` runs all three
gates and is wired into `make verify`; CI runs them in the
"Python tests + Gutenberg e2e smoke" job.

## The seven content drops (all fixed)

1–2. **Python Bricks converter** had no settings branches for testimonial,
call-to-action, icon-box, price-table, alert, blockquote, icon-list,
gallery, or social-icons — testimonial bodies/names and CTA titles (among
others) silently dropped. All content-bearing widget categories now map to
real Bricks settings.

3. **PHP Bricks converter** emitted gallery images as an escaped JSON-string
blob; it now emits the real Bricks `images` array (`[{url, id}, ...]`).

4–5. **Python Gutenberg converter** sent universal container components
(from PHP parsers) down the marker path, dropping **all** their children;
containers now recurse into group blocks. It also learned the PHP parsers'
universal attribute vocabulary (`label`, `image_url`, `heading`, `author`,
`job_title`, ...), translating it to the widget builders' expected keys.

6–7. **PHP Gutenberg converter** dropped button text carried in `label`
attributes (DIVI/WPBakery sources), single-panel toggle headings/bodies
(DIVI `et_pb_toggle`), and DIVI testimonial author/job/company citations.

## Test coverage

| | v4.7.0 | v4.8.0 |
|---|---|---|
| E2e smoke gates | 1 (Elementor → Gutenberg) | **3** (+ Elementor → Bricks, DIVI → Gutenberg) |
| Python tests | 156 | **156** |
| PHP tests | 328 / 5,627 assertions | **328 / 5,627 assertions** |
| Failures / errors | 0 / 0 | **0 / 0** |

## Compatibility

- The content-drop fixes strictly add output that was previously missing;
  no existing correct output changes.
- No changes to parsers, the REST API, or the CLI surface.
- PHP 8.1+ / Python 3.9+ floors unchanged.

## What's next (from the roadmap)

Responsive canonicalization for Elementor v3 / Bricks breakpoints, and
Python parsers for the remaining frameworks.
