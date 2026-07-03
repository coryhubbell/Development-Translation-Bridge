"""
End-to-end fidelity smoke gate: Elementor → Bricks (both engines).

Runs the kitchen-sink Elementor fixture through the Python v4 BricksConverter
and the PHP v3 pipeline (Elementor parser → Bricks converter), then validates
the Bricks-specific failure modes:

  1. Output is the real Bricks 2.x FLAT page format: a list of elements with
     string `parent` ids and `children` lists of id strings (never nested
     objects) — the v4.3.0 correctness guarantee.
  2. Structural integrity: every parent/child id resolves to a real element;
     no element has an empty name.
  3. Content survival: distinctive strings from every content-bearing widget
     category in the fixture appear in the output (testimonial bodies, CTA
     titles, pricing features, etc. — the drops this gate was built to catch).

Run with: python3 tests/smoke_bricks_e2e.py
Exit code: 0 if all assertions pass, 1 otherwise.
"""

from __future__ import annotations

import json
import sys
from pathlib import Path
from typing import Any, Dict, List

REPO_ROOT = Path(__file__).resolve().parent.parent
FIXTURE = REPO_ROOT / "tests" / "fixtures" / "elementor" / "kitchen-sink.json"

sys.path.insert(0, str(REPO_ROOT / "src"))
sys.path.insert(0, str(REPO_ROOT / "tests"))

from smoke_lib import php_prologue, run_php_harness, strip_php_banner  # noqa: E402

from translation_bridge.converters.bricks import BricksConverter  # noqa: E402


# Content strings that must survive on BOTH engines. Composed strings (like
# Gutenberg's "$49 / mo") don't exist in Bricks — the parts survive natively.
SANITY_STRINGS = [
    "Kitchen Sink Hero",          # heading
    "Get started",                # button
    "Zero downtime deploys",      # icon-list item
    "Uptime SLA",                 # counter title
    "Ready to ship?",             # cta title
    "Audit logs",                 # pricing feature
    "We cut migration time",      # testimonial body
    "Jane Doe",                   # testimonial name
    "Migrations complete",        # alert title
    "All 142 pages converted.",   # alert body
    "https://example.com/img/2.jpg",  # gallery image
    "twitter",                    # social-icon service
    "William Gibson",             # blockquote cite
]


def run_python_converter() -> str:
    fixture = json.loads(FIXTURE.read_text())
    return BricksConverter().convert(fixture)


PHP_HARNESS_BODY = r"""
use DEVTB\TranslationBridge\Parsers\DEVTB_Elementor_Parser;
use DEVTB\TranslationBridge\Converters\DEVTB_Bricks_Converter;

$json = file_get_contents('{fixture}');
$parser = new DEVTB_Elementor_Parser();
$components = $parser->parse($json);

$converter = new DEVTB_Bricks_Converter();
echo $converter->convert($components);
"""


def run_php_converter() -> str:
    source = php_prologue() + PHP_HARNESS_BODY.format(fixture=str(FIXTURE))
    return strip_php_banner(run_php_harness(source, "_smoke_bricks_harness.php"))


# --- assertions -----------------------------------------------------------

def validate(engine: str, output: str) -> List[str]:
    failures: List[str] = []

    try:
        decoded = json.loads(output)
    except json.JSONDecodeError as exc:
        return [f"output is not valid JSON: {exc}"]

    elements = decoded if isinstance(decoded, list) else decoded.get("content")
    if not isinstance(elements, list) or not elements:
        return ["output is not a non-empty element list"]

    by_id: Dict[str, Dict[str, Any]] = {}
    for i, element in enumerate(elements):
        if not isinstance(element, dict):
            failures.append(f"element [{i}] is not an object")
            continue
        if not element.get("name"):
            failures.append(f"element [{i}] has an empty name")
        element_id = element.get("id")
        if not isinstance(element_id, str) or not element_id:
            failures.append(f"element [{i}] has a missing/non-string id")
            continue
        by_id[element_id] = element

    for element_id, element in by_id.items():
        # Flat-format guarantee: children are id strings, never objects.
        children = element.get("children", [])
        if not isinstance(children, list):
            failures.append(f"element {element_id}: children is not a list")
            continue
        for child in children:
            if isinstance(child, dict):
                failures.append(
                    f"element {element_id}: nested child OBJECT found — output is not the flat 2.x format"
                )
            elif str(child) not in by_id:
                failures.append(f"element {element_id}: child id '{child}' does not resolve")

        parent = element.get("parent", 0)
        if parent not in (0, "0", "", None) and str(parent) not in by_id:
            failures.append(f"element {element_id}: parent id '{parent}' does not resolve")

    for needle in SANITY_STRINGS:
        if needle not in output:
            failures.append(f"content '{needle}' did not survive conversion")

    return failures


# --- main -----------------------------------------------------------------

def main() -> int:
    print(f"Fixture: {FIXTURE.relative_to(REPO_ROOT)}")
    print("Gate: Elementor → Bricks (flat-format + content survival)\n")

    overall_ok = True
    for engine, runner in (("python", run_python_converter), ("php", run_php_converter)):
        print(f"--- {engine.upper()} engine ---")
        try:
            output = runner()
        except Exception as exc:  # noqa: BLE001
            print(f"  ERROR: {engine} engine failed to run: {exc}\n")
            overall_ok = False
            continue

        failures = validate(engine, output)
        element_count = output.count('"name"')
        print(f"  elements emitted: {element_count}")
        if failures:
            overall_ok = False
            print(f"  FAILURES ({len(failures)}):")
            for failure in failures:
                print(f"    - {failure}")
        else:
            print("  PASS: all assertions hold")
        print()

    if overall_ok:
        print("RESULT: both engines pass the Elementor → Bricks smoke gate")
        return 0
    print("RESULT: Elementor → Bricks smoke gate FAILED")
    return 1


if __name__ == "__main__":
    sys.exit(main())
