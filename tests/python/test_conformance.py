"""RFC 5.0 Phase 1 — dual-engine conformance suite.

Shared real fixtures are parsed by BOTH engines; the outputs must
(a) validate against the canonical universal-element schema
(schema/universal-element.schema.json — asserted structurally here to keep
the suite dependency-free), and (b) agree on extracted content.
"""

import json
import subprocess
import sys
from pathlib import Path

import pytest

REPO_ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(REPO_ROOT / "tests"))

from smoke_lib import php_prologue, run_php_harness, strip_php_banner  # noqa: E402


# ---------------------------------------------------------------------------
# Schema shape assertions (mirrors schema/universal-element.schema.json)
# ---------------------------------------------------------------------------

_EL_TYPES = {"section", "container", "column", "widget"}
_BREAKPOINTS = {"desktop", "tablet", "phone"}
_STATES = {"default", "hover"}


def assert_universal_element(element, path="root"):
    assert isinstance(element, dict), f"{path}: element is not an object"
    assert element.get("elType") in _EL_TYPES, f"{path}: bad elType {element.get('elType')!r}"
    if element["elType"] == "widget":
        assert isinstance(element.get("widgetType"), str) and element["widgetType"], (
            f"{path}: widget without widgetType"
        )
    assert isinstance(element.get("settings"), dict), f"{path}: settings must be an object"
    assert isinstance(element.get("elements"), list), f"{path}: elements must be a list"

    responsive = element.get("responsive")
    if responsive is not None:
        assert isinstance(responsive, dict), f"{path}: responsive must be an object"
        assert set(responsive.keys()) <= {"styles", "fields"}, f"{path}: bad responsive keys"
        maps = []
        if isinstance(responsive.get("styles"), dict):
            maps.append(responsive["styles"])
        if isinstance(responsive.get("fields"), dict):
            maps.extend(v for v in responsive["fields"].values() if isinstance(v, dict))
        for bp_map in maps:
            assert set(bp_map.keys()) <= _BREAKPOINTS, f"{path}: bad breakpoint keys {bp_map.keys()}"
            for states in bp_map.values():
                assert set(states.keys()) <= _STATES, f"{path}: bad state keys {states.keys()}"

    for i, child in enumerate(element["elements"]):
        assert_universal_element(child, f"{path}/elements[{i}]")


def collect_text(element, out):
    for value in element.get("settings", {}).values():
        if isinstance(value, str) and value.strip():
            out.append(value.strip())
        elif isinstance(value, dict):
            for sub in value.values():
                if isinstance(sub, str) and sub.strip():
                    out.append(sub.strip())
        elif isinstance(value, list):
            for item in value:
                if isinstance(item, dict):
                    for sub in item.values():
                        if isinstance(sub, str) and sub.strip():
                            out.append(sub.strip())
    for child in element.get("elements", []):
        collect_text(child, out)


# ---------------------------------------------------------------------------
# PHP-side universal export
# ---------------------------------------------------------------------------

PHP_UNIVERSAL_HARNESS = r"""
use DEVTB\TranslationBridge\Parsers\{parser_class};

$content = file_get_contents('{fixture}');
$parser = new {parser_class}();
$components = $parser->parse($content);
echo json_encode(array_map(fn($c) => $c->to_universal(), $components));
"""


def php_universal(parser_class: str, fixture: Path) -> list:
    source = php_prologue() + PHP_UNIVERSAL_HARNESS.format(
        parser_class=parser_class, fixture=str(fixture)
    )
    output = strip_php_banner(run_php_harness(source, "_conformance_harness.php"))
    return json.loads(output)


# ---------------------------------------------------------------------------
# Conformance cases: (fixture, PHP parser class, python parser factory, sanity strings)
# ---------------------------------------------------------------------------


def _python_elementor(path):
    from translation_bridge.parsers.elementor import ElementorParser

    return ElementorParser().parse(json.loads(path.read_text())).to_dict()["elements"]


def _python_divi(path):
    from translation_bridge.parsers.divi import DiviParser

    return DiviParser().parse(path.read_text()).to_dict()["elements"]


def _python_oxygen6(path):
    from translation_bridge.parsers.oxygen6 import Oxygen6Parser

    return Oxygen6Parser().parse(json.loads(path.read_text())).to_dict()["elements"]


CASES = [
    pytest.param(
        "tests/fixtures/elementor/kitchen-sink.json",
        "DEVTB_Elementor_Parser",
        _python_elementor,
        ["Kitchen Sink Hero", "Get started", "We cut migration time"],
        id="elementor-kitchen-sink",
    ),
    pytest.param(
        "tests/fixtures/divi/kitchen-sink.txt",
        "DEVTB_DIVI_Parser",
        _python_divi,
        ["DIVI Kitchen Sink", "Start migration", "Maya Chen"],
        id="divi-kitchen-sink",
    ),
    pytest.param(
        "tests/fixtures/oxygen6/breakdance-element-export.json",
        "DEVTB_Oxygen6_Parser",
        _python_oxygen6,
        ["Genre:"],
        id="breakdance-real-export",
    ),
]


@pytest.mark.parametrize("fixture,php_parser,python_parse,sanity", CASES)
class TestDualEngineConformance:
    def test_php_engine_emits_schema_valid_universal(self, fixture, php_parser, python_parse, sanity):
        elements = php_universal(php_parser, REPO_ROOT / fixture)
        assert elements, "PHP engine produced no elements"
        for i, element in enumerate(elements):
            assert_universal_element(element, f"php[{i}]")

    def test_python_engine_emits_schema_valid_universal(self, fixture, php_parser, python_parse, sanity):
        elements = python_parse(REPO_ROOT / fixture)
        assert elements, "Python engine produced no elements"
        for i, element in enumerate(elements):
            assert_universal_element(element, f"py[{i}]")

    def test_engines_agree_on_content(self, fixture, php_parser, python_parse, sanity):
        php_text: list = []
        for element in php_universal(php_parser, REPO_ROOT / fixture):
            collect_text(element, php_text)
        py_text: list = []
        for element in python_parse(REPO_ROOT / fixture):
            collect_text(element, py_text)

        php_blob = "\n".join(php_text)
        py_blob = "\n".join(py_text)
        for needle in sanity:
            assert needle in php_blob, f"PHP engine lost content: {needle}"
            assert needle in py_blob, f"Python engine lost content: {needle}"
