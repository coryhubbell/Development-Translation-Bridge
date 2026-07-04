"""
Translation Bridge v4 - Bootstrap HTML Source Parser.

Parses Bootstrap 5 HTML (the project's universal output format) back into
the universal element shape, closing the loop: Bootstrap output from any
converter can re-enter the lossless ``transform`` path as a source.
"""

from __future__ import annotations

from typing import Any, Dict, List

from translation_bridge.parsers.htmlbase import HTMLSourceParser, _classes  # noqa: F401
from translation_bridge.parsers.universal import (
    UniversalDocument,
    analyze_document,
    extract_document_content,
)
from translation_bridge.transforms.registry import ParserRegistry


@ParserRegistry.register(
    name="bootstrap_parser",
    framework="bootstrap",
    description="Bootstrap 5 HTML parser",
    version="4.11.0",
    file_extensions=[".html"],
)
class BootstrapParser(HTMLSourceParser):
    """Parse Bootstrap HTML into a UniversalDocument."""

    framework = "bootstrap"
    button_class_hints = ("btn",)
    card_class_hints = ("card",)

    def _container_settings(self, node) -> Dict[str, Any]:
        settings: Dict[str, Any] = {}
        classes = _classes(node)
        for cls in classes:
            if cls.startswith("bg-"):
                settings["background_token"] = cls[3:]
        return settings

    # CLI surface -------------------------------------------------------

    def extract_content(self, doc: UniversalDocument) -> Dict[str, List[str]]:
        return extract_document_content(doc)

    def analyze(self, doc: UniversalDocument) -> Dict[str, Any]:
        return analyze_document(doc)

    def get_framework(self) -> str:
        return "bootstrap"
