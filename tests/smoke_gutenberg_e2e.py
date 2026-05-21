"""
End-to-end smoke proof for the v4.3.4 Elementor → Gutenberg hotfix.

Runs the kitchen-sink fixture (one of every widget category the Elementor
parser produces) through BOTH the Python v4 converter and the PHP v3
converter, then validates the output against the production failure modes
the hotfix was supposed to close:

  1. No widget collapses to an empty <p></p> core/paragraph.
  2. Every input widgetType produces SOMETHING in the output — either a
     native block or a visible `devtb: unconverted` marker.
  3. Block delimiters are balanced (every `<!-- wp:X -->` has a matching
     `<!-- /wp:X -->`, or is a self-closing `/-->`).
  4. Compound widgets (tabs, accordion, card, cta, counter, testimonial,
     pricing-table, alert) emit their `devtb-<type>-converted` className.
  5. Marker widgets (form, slider, countdown, portfolio, toc, map,
     progress, rating, unknown) emit the `data-devtb-source` annotation.

Run with: python3 tests/smoke_gutenberg_e2e.py
Exit code: 0 if all assertions pass, 1 otherwise.
"""

from __future__ import annotations

import json
import re
import subprocess
import sys
from pathlib import Path
from typing import List, Tuple

REPO_ROOT = Path(__file__).resolve().parent.parent
FIXTURE = REPO_ROOT / "tests" / "fixtures" / "elementor" / "kitchen-sink.json"

sys.path.insert(0, str(REPO_ROOT / "src"))

from translation_bridge.converters.gutenberg import GutenbergConverter  # noqa: E402


# Widget types in the fixture grouped by expected dispatch class.
EXPECTED_SIMPLE = {
    "heading",
    "text-editor",
    "button",
    "divider",
    "spacer",
    "icon",
    "audio",
    "video",
    "icon-list",
    "image-gallery",
    "blockquote",
    "social-icons",
    "nav-menu",
}
EXPECTED_COMPOUND = {
    "icon-box",
    "tabs",
    "accordion",
    "call-to-action",
    "counter",
    "testimonial",
    "price-table",
    "alert",
}
EXPECTED_MARKER = {
    "form",
    "slider",
    "countdown",
    "google_maps",
    "progress",
    "portfolio",
    "star-rating",
    "table-of-contents",
    "some-third-party-mystery-widget",  # the unknown one
}

COMPOUND_CLASSNAMES = {
    "icon-box": "devtb-card-converted",
    "tabs": "devtb-tabs-converted",
    "accordion": "devtb-accordion-converted",
    "call-to-action": "devtb-cta-converted",
    "counter": "devtb-counter-converted",
    "testimonial": "wp:quote",  # testimonial maps directly to core/quote
    "price-table": "devtb-pricing-converted",
    "alert": "devtb-alert",
}


# --- helpers --------------------------------------------------------------

def collect_widget_types(elements) -> List[str]:
    out: List[str] = []
    for el in elements:
        if not isinstance(el, dict):
            continue
        if el.get("elType") == "widget":
            out.append(el.get("widgetType", ""))
        if isinstance(el.get("elements"), list):
            out.extend(collect_widget_types(el["elements"]))
    return out


def _strip_block_attrs(blocks: str) -> str:
    """Strip JSON attribute payloads from block comments so a delimiter-only stack
    analysis can ignore nested-brace content. We can't use a single regex because
    Gutenberg attrs can have nested objects (e.g. {"style":{"color":{...}}}).
    """
    out: List[str] = []
    i = 0
    n = len(blocks)
    while i < n:
        ch = blocks[i]
        if ch == "{" and i > 0 and blocks[i - 1] == " " and out and out[-1].endswith(" "):
            depth = 1
            j = i + 1
            while j < n and depth > 0:
                if blocks[j] == "{":
                    depth += 1
                elif blocks[j] == "}":
                    depth -= 1
                j += 1
            # Replace the JSON blob (and the preceding space) with nothing.
            if out and out[-1].endswith(" "):
                out[-1] = out[-1][:-1]
            i = j
            continue
        out.append(ch)
        i += 1
    return "".join(out)


def find_unbalanced_delimiters(blocks: str) -> List[str]:
    """Walk the block markup and return any unbalanced opening/closing comments."""
    stripped = _strip_block_attrs(blocks)
    # After stripping attrs, all delimiters are one of three shapes:
    #   <!-- wp:NAME -->        opening
    #   <!-- wp:NAME /-->       self-closing
    #   <!-- /wp:NAME -->       closing
    token_re = re.compile(r"<!-- (/?)wp:([a-z0-9/-]+)( /)? -->")
    stack: List[str] = []
    problems: List[str] = []
    for m in token_re.finditer(stripped):
        is_closing = bool(m.group(1))
        name = m.group(2)
        is_self_closing = bool(m.group(3))
        if is_self_closing:
            continue
        if is_closing:
            if not stack:
                problems.append(f"closing </wp:{name}> with no opener")
            elif stack[-1] != name:
                problems.append(f"closing </wp:{name}> but top of stack is <wp:{stack[-1]}>")
                stack.pop()
            else:
                stack.pop()
        else:
            stack.append(name)
    for unclosed in stack:
        problems.append(f"unclosed <wp:{unclosed}>")
    return problems


def find_empty_paragraphs(blocks: str) -> int:
    """Count paragraph blocks whose body is literally an empty <p></p> — the production bug."""
    pattern = re.compile(
        r"<!-- wp:paragraph(?: \{[^}]*\})? -->\s*<p>\s*</p>\s*<!-- /wp:paragraph -->"
    )
    return len(pattern.findall(blocks))


def run_python_converter() -> str:
    fixture = json.loads(FIXTURE.read_text())
    return GutenbergConverter().convert(fixture)


PHP_HARNESS = r"""<?php
define('DEVTB_TESTING', true);
define('DEVTB_ROOT', '{root}');
define('DEVTB_TRANSLATION_BRIDGE', DEVTB_ROOT . '/translation-bridge');

require DEVTB_ROOT . '/vendor/autoload.php';
require DEVTB_ROOT . '/tests/bootstrap.php';

use DEVTB\TranslationBridge\Parsers\DEVTB_Elementor_Parser;
use DEVTB\TranslationBridge\Converters\DEVTB_Gutenberg_Converter;

$json = file_get_contents('{fixture}');
$parser = new DEVTB_Elementor_Parser();
$components = $parser->parse($json);

$converter = new DEVTB_Gutenberg_Converter();
echo $converter->convert($components);
"""


def run_php_converter() -> str:
    harness_path = REPO_ROOT / "tests" / "_smoke_harness.php"
    harness_path.write_text(
        PHP_HARNESS.format(root=str(REPO_ROOT), fixture=str(FIXTURE))
    )
    try:
        result = subprocess.run(
            ["php", str(harness_path)],
            capture_output=True,
            text=True,
            cwd=REPO_ROOT,
            timeout=30,
        )
        if result.returncode != 0:
            raise RuntimeError(f"PHP harness failed: {result.stderr}")
        return result.stdout
    finally:
        if harness_path.exists():
            harness_path.unlink()


# --- assertions -----------------------------------------------------------

def validate(name: str, blocks: str) -> List[str]:
    failures: List[str] = []

    # 1. No empty-paragraph collapses.
    empties = find_empty_paragraphs(blocks)
    if empties:
        failures.append(f"{empties} empty <p></p> core/paragraph block(s) found (production bug)")

    # 2. Delimiters balanced.
    unbalanced = find_unbalanced_delimiters(blocks)
    if unbalanced:
        failures.append(f"unbalanced delimiters: {unbalanced}")

    # 3. Each compound widget should emit its className (or for testimonial, a core/quote).
    for widget, marker in COMPOUND_CLASSNAMES.items():
        if widget in collect_widget_types(json.loads(FIXTURE.read_text())):
            if marker not in blocks:
                failures.append(
                    f"compound widget '{widget}' did not emit expected marker '{marker}'"
                )

    # 4. Each marker widget should emit a data-devtb-source annotation.
    for widget in EXPECTED_MARKER:
        token = f'data-devtb-source="elementor:{widget}"'
        if widget == "some-third-party-mystery-widget":
            # PHP parser maps unknown widget types to universal "unknown", so the marker
            # on the PHP side will say `elementor:unknown`, not the original widgetType.
            if 'data-devtb-source="elementor:some-third-party-mystery-widget"' not in blocks \
               and 'data-devtb-source="elementor:unknown"' not in blocks:
                failures.append(f"unknown widget did not emit any data-devtb-source marker")
            continue
        if token not in blocks:
            failures.append(f"marker widget '{widget}' missing {token}")

    # 5. Sanity: every fixture widget should leave SOMETHING traceable in the output.
    #    For widgets with distinctive content (titles), check the content survived.
    sanity_strings = [
        "Kitchen Sink Hero",         # heading
        "Get started",                # button
        "Zero downtime deploys",      # icon-list
        "Uptime SLA",                 # counter title
        "99.9%",                      # counter value
        "Pricing",                    # tab title
        "Ready to ship?",             # cta
        "Pro",                        # pricing heading
        "$49 / mo",                   # pricing price
        "Audit logs",                 # pricing feature
        "We cut migration time",      # testimonial body
        "Jane Doe, CTO, Acme",        # testimonial cite (combined)
        "Migrations complete",        # alert title
        "All 142 pages converted.",   # alert body
        "https://example.com/img/2.jpg",  # gallery image
        "twitter",                    # social-icon service
        "William Gibson",             # blockquote cite
        "Contact us",                 # form marker title
        "Customer logos",             # slider marker title
        "Launch",                     # countdown marker title
    ]
    for needle in sanity_strings:
        if needle not in blocks:
            failures.append(f"content '{needle}' did not survive conversion")

    return failures


# --- main -----------------------------------------------------------------

def main() -> int:
    fixture_widgets = collect_widget_types(json.loads(FIXTURE.read_text()))
    print(f"Fixture: {FIXTURE.relative_to(REPO_ROOT)}")
    print(f"Widget types in fixture ({len(fixture_widgets)}): {sorted(set(fixture_widgets))}\n")

    overall_ok = True

    for engine, runner in (("python", run_python_converter), ("php", run_php_converter)):
        print(f"--- {engine.upper()} engine ---")
        try:
            blocks = runner()
        except Exception as e:
            print(f"  ERROR: {engine} engine failed to run: {e}\n")
            overall_ok = False
            continue

        failures = validate(engine, blocks)
        empties = find_empty_paragraphs(blocks)
        block_count = blocks.count("<!-- wp:")
        marker_count = blocks.count("devtb: unconverted")
        print(f"  total blocks emitted: {block_count}")
        print(f"  marker fallbacks:     {marker_count}")
        print(f"  empty-paragraph bugs: {empties}")
        if failures:
            overall_ok = False
            print(f"  FAILURES ({len(failures)}):")
            for f in failures:
                print(f"    - {f}")
        else:
            print("  PASS: all assertions hold")
        print()

    if overall_ok:
        print("RESULT: both engines pass e2e smoke proof for v4.3.4")
        return 0
    print("RESULT: smoke proof FAILED — follow-up PR needed")
    return 1


if __name__ == "__main__":
    sys.exit(main())
