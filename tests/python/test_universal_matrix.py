"""Cross-source universal fidelity matrix (pre-5.0 converter hardening).

Every Python converter must consume a universal document from ANY source
parser without losing content — the Python analog of the PHP engine's
182-pair FrameworkConversionsTest. Sources are the three real conformance
fixtures; the gate is measure_fidelity ≥ MIN_RATIO per pair.
"""

import json
from pathlib import Path

import pytest

from translation_bridge.cli import get_converter_for_framework, measure_fidelity

REPO_ROOT = Path(__file__).resolve().parents[2]

# Content-survival floor for every source × target pair. Marker-style
# widgets may legitimately drop a string or two (countdown dates, map
# addresses), so the floor is 0.9 rather than 1.0.
MIN_RATIO = 0.9

TARGETS = [
    "elementor",
    "elementor-4",
    "bootstrap",
    "gutenberg",
    "bricks",
    "oxygen",
    "oxygen-6",
    "divi",
    "divi-5",
    "wpbakery",
    "avada",
    "kadence",
    "beaver-builder",
    "thrive",
]


def _elementor_doc():
    from translation_bridge.parsers.elementor import ElementorParser

    fixture = REPO_ROOT / "tests/fixtures/elementor/kitchen-sink.json"
    return ElementorParser().parse(json.loads(fixture.read_text())).to_dict()


def _divi_doc():
    from translation_bridge.parsers.divi import DiviParser

    fixture = REPO_ROOT / "tests/fixtures/divi/kitchen-sink.txt"
    return DiviParser().parse(fixture.read_text()).to_dict()


def _oxygen6_doc():
    from translation_bridge.parsers.oxygen6 import Oxygen6Parser

    fixture = REPO_ROOT / "tests/fixtures/oxygen6/breakdance-element-export.json"
    return Oxygen6Parser().parse(json.loads(fixture.read_text())).to_dict()


SOURCES = {
    "elementor": _elementor_doc,
    "divi": _divi_doc,
    "oxygen6": _oxygen6_doc,
}


@pytest.fixture(scope="module")
def source_docs():
    return {name: build() for name, build in SOURCES.items()}


@pytest.mark.parametrize("source", list(SOURCES))
@pytest.mark.parametrize("target", TARGETS)
def test_universal_document_converts_with_high_fidelity(source_docs, source, target):
    if source == target or (source == "oxygen6" and target == "oxygen-6"):
        pytest.skip("same-framework pair")

    doc = source_docs[source]
    converter = get_converter_for_framework(target)
    assert converter is not None, f"no converter for {target}"

    output = converter.convert(doc)
    assert output, f"{source} → {target}: empty output"

    fidelity = measure_fidelity(doc, output)
    assert fidelity["ratio"] >= MIN_RATIO, (
        f"{source} → {target}: {fidelity['content_preserved']}/{fidelity['content_total']} "
        f"content strings preserved ({fidelity['ratio'] * 100:.0f}%)"
    )
