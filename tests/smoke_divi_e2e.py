"""
End-to-end fidelity smoke gate: DIVI → Gutenberg (both converter engines).

DIVI is shortcode-based, so source parsing happens in PHP. The gate parses
the DIVI kitchen-sink fixture once with the PHP DIVI parser, then drives
BOTH Gutenberg converters:

  - PHP:    components → DEVTB_Gutenberg_Converter
  - Python: the same parsed components (exported as universal component
    dicts via DEVTB_Component::to_array) → GutenbergConverter

Validated failure modes (mirroring the v4.3.4 gate):

  1. No empty <p></p> core/paragraph collapses.
  2. Block delimiters balanced.
  3. Every content-bearing DIVI module's text survives conversion.

Run with: python3 tests/smoke_divi_e2e.py
Exit code: 0 if all assertions pass, 1 otherwise.
"""

from __future__ import annotations

import json
import sys
from pathlib import Path
from typing import List

REPO_ROOT = Path(__file__).resolve().parent.parent
FIXTURE = REPO_ROOT / "tests" / "fixtures" / "divi" / "kitchen-sink.txt"

sys.path.insert(0, str(REPO_ROOT / "src"))
sys.path.insert(0, str(REPO_ROOT / "tests"))

from smoke_lib import (  # noqa: E402
    find_empty_paragraphs,
    find_unbalanced_delimiters,
    php_prologue,
    run_php_harness,
    strip_php_banner,
)

from translation_bridge.converters.gutenberg import GutenbergConverter  # noqa: E402


# Distinctive content per module category that must survive on both engines.
SANITY_STRINGS = [
    "DIVI Kitchen Sink",                     # text (h1)
    "Every module category",                 # text (p)
    "Start migration",                       # button 1
    "Read the docs",                         # button 2
    "https://example.com/img/divi-hero.jpg", # image src
    "Zero-loss transforms",                  # blurb title
    "keep every byte of metadata",           # blurb body
    "How long does it take?",                # toggle title
    "About half a second per page",          # toggle body
    "Pages migrated",                        # counter title
    "The DIVI to Gutenberg move",            # testimonial body
    "Maya Chen",                             # testimonial author
    "Ship the migration",                    # cta title
    "Your content is portable now.",         # cta body
    "wp post list --post_type=page",         # code
    "Launch window",                         # countdown title
    "Talk to us",                            # contact form title
]


PHP_PARSE_HARNESS = r"""
use DEVTB\TranslationBridge\Parsers\DEVTB_DIVI_Parser;

$content = file_get_contents('{fixture}');
$parser = new DEVTB_DIVI_Parser();
$components = $parser->parse($content);
echo json_encode(array_map(fn($c) => $c->to_array(), $components));
"""

PHP_CONVERT_HARNESS = r"""
use DEVTB\TranslationBridge\Parsers\DEVTB_DIVI_Parser;
use DEVTB\TranslationBridge\Converters\DEVTB_Gutenberg_Converter;

$content = file_get_contents('{fixture}');
$parser = new DEVTB_DIVI_Parser();
$components = $parser->parse($content);

$converter = new DEVTB_Gutenberg_Converter();
echo $converter->convert($components);
"""


def parse_components_via_php() -> list:
    source = php_prologue() + PHP_PARSE_HARNESS.format(fixture=str(FIXTURE))
    output = strip_php_banner(run_php_harness(source, "_smoke_divi_parse_harness.php"))
    return json.loads(output)


def run_php_pipeline() -> str:
    source = php_prologue() + PHP_CONVERT_HARNESS.format(fixture=str(FIXTURE))
    return strip_php_banner(run_php_harness(source, "_smoke_divi_convert_harness.php"))


def run_python_converter() -> str:
    components = parse_components_via_php()
    return GutenbergConverter().convert(components)


# --- assertions -----------------------------------------------------------

def validate(engine: str, blocks: str) -> List[str]:
    failures: List[str] = []

    empties = find_empty_paragraphs(blocks)
    if empties:
        failures.append(f"{empties} empty <p></p> core/paragraph block(s) found")

    unbalanced = find_unbalanced_delimiters(blocks)
    if unbalanced:
        failures.append(f"unbalanced delimiters: {unbalanced}")

    for needle in SANITY_STRINGS:
        if needle not in blocks:
            failures.append(f"content '{needle}' did not survive conversion")

    return failures


# --- main -----------------------------------------------------------------

def main() -> int:
    print(f"Fixture: {FIXTURE.relative_to(REPO_ROOT)}")
    print("Gate: DIVI → Gutenberg (content survival + block integrity)\n")

    overall_ok = True
    for engine, runner in (("python", run_python_converter), ("php", run_php_pipeline)):
        print(f"--- {engine.upper()} engine ---")
        try:
            blocks = runner()
        except Exception as exc:  # noqa: BLE001
            print(f"  ERROR: {engine} engine failed to run: {exc}\n")
            overall_ok = False
            continue

        failures = validate(engine, blocks)
        print(f"  total blocks emitted: {blocks.count('<!-- wp:')}")
        print(f"  empty-paragraph bugs: {find_empty_paragraphs(blocks)}")
        if failures:
            overall_ok = False
            print(f"  FAILURES ({len(failures)}):")
            for failure in failures:
                print(f"    - {failure}")
        else:
            print("  PASS: all assertions hold")
        print()

    if overall_ok:
        print("RESULT: both engines pass the DIVI → Gutenberg smoke gate")
        return 0
    print("RESULT: DIVI → Gutenberg smoke gate FAILED")
    return 1


if __name__ == "__main__":
    sys.exit(main())
