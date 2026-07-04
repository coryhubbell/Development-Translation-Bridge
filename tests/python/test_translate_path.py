"""RFC 5.0 Phase 3 — translate-path deprecation (Python surfaces).

'devtb translate' is a deprecated alias riding the same lossless path as
transform; unregistered pairs go through the universal route (any parsed
document → any converter) behind a fidelity gate; fidelity metrics are
reported per conversion.
"""

import json
import subprocess
import sys
from pathlib import Path

import pytest

from translation_bridge.cli import (
    collect_content_strings,
    get_converter_for_framework,
    measure_fidelity,
)

REPO_ROOT = Path(__file__).resolve().parents[2]


def run_cli(*args):
    return subprocess.run(
        [sys.executable, "-m", "translation_bridge.cli", *args],
        capture_output=True,
        text=True,
        cwd=REPO_ROOT,
        env={"PYTHONPATH": str(REPO_ROOT / "src"), "PATH": "/usr/bin:/bin"},
    )


class TestConverterResolver:
    ALL_TARGETS = [
        "elementor", "elementor-4", "bootstrap", "gutenberg", "bricks",
        "oxygen", "oxygen-6", "divi", "divi-5", "wpbakery", "avada",
        "kadence", "beaver-builder", "thrive",
    ]

    @pytest.mark.parametrize("framework", ALL_TARGETS)
    def test_every_framework_resolves_a_converter(self, framework):
        converter = get_converter_for_framework(framework)
        assert converter is not None, f"no converter for {framework}"
        assert hasattr(converter, "convert")

    def test_unknown_framework_resolves_none(self):
        assert get_converter_for_framework("wix") is None


class TestFidelityMetric:
    def doc(self, settings):
        return {
            "elements": [
                {
                    "elType": "widget",
                    "widgetType": "heading",
                    "settings": settings,
                    "elements": [],
                }
            ]
        }

    def test_full_preservation(self):
        doc = self.doc({"title": "Hello World"})
        fidelity = measure_fidelity(doc, "<h2>Hello World</h2>")
        assert fidelity == {"content_total": 1, "content_preserved": 1, "ratio": 1.0}

    def test_loss_is_counted(self):
        doc = self.doc({"title": "Hello World", "description": "Gone entirely"})
        fidelity = measure_fidelity(doc, "<h2>Hello World</h2>")
        assert fidelity["content_total"] == 2
        assert fidelity["content_preserved"] == 1
        assert fidelity["ratio"] == 0.5

    def test_empty_document_is_perfect(self):
        assert measure_fidelity({"elements": []}, "") == {
            "content_total": 0,
            "content_preserved": 0,
            "ratio": 1.0,
        }

    def test_json_output_counts_as_haystack(self):
        doc = self.doc({"title": "Nested Value"})
        fidelity = measure_fidelity(doc, {"deep": [{"label": "Nested Value"}]})
        assert fidelity["ratio"] == 1.0

    def test_style_enums_are_not_content(self):
        strings: list = []
        collect_content_strings(
            {
                "elType": "widget",
                "widgetType": "button",
                "settings": {
                    "text": "Press me",
                    "layout": "boxed",
                    "button_type": "primary",
                    "link": {"url": "https://x.test", "is_external": "on"},
                },
                "elements": [],
            },
            strings,
        )
        assert "Press me" in strings
        assert "https://x.test" in strings
        assert "boxed" not in strings
        assert "primary" not in strings
        assert "on" not in strings

    def test_list_item_content_is_collected(self):
        strings: list = []
        collect_content_strings(
            {
                "elType": "widget",
                "widgetType": "tabs",
                "settings": {"tabs": [{"tab_title": "T1", "tab_content": "Body 1"}]},
                "elements": [],
            },
            strings,
        )
        assert strings == ["T1", "Body 1"]


class TestTranslateAlias:
    def test_translate_is_a_deprecated_alias_of_transform(self):
        result = run_cli(
            "translate", "elementor", "gutenberg",
            "tests/fixtures/elementor/kitchen-sink.json", "-n",
        )
        assert result.returncode == 0, result.stderr
        assert "deprecated" in result.stdout
        assert "Fidelity:" in result.stdout
        assert "Kitchen Sink Hero" in result.stdout

    def test_transform_reports_fidelity_per_conversion(self):
        result = run_cli(
            "transform", "divi", "gutenberg",
            "tests/fixtures/divi/kitchen-sink.txt", "-n",
        )
        assert result.returncode == 0, result.stderr
        assert "deprecated" not in result.stdout
        assert "Fidelity:" in result.stdout


class TestUniversalRouteGate:
    def test_unregistered_pair_uses_universal_route(self):
        result = run_cli(
            "transform", "divi", "elementor",
            "tests/fixtures/divi/kitchen-sink.txt", "-n",
        )
        assert result.returncode == 0, result.stderr
        assert "universal route" in result.stdout
        assert "falling back" not in result.stdout
        assert "Fidelity:" in result.stdout

    def test_low_fidelity_pair_falls_back_to_content_extraction(self):
        result = run_cli(
            "transform", "divi", "kadence",
            "tests/fixtures/divi/kitchen-sink.txt", "-n",
        )
        assert result.returncode == 0, result.stderr
        assert "universal route" in result.stdout
        assert "falling back to content extraction" in result.stdout
        assert "devtb translate" in result.stdout
