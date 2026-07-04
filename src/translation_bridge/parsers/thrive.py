"""
Translation Bridge v4 - Thrive Architect (TCB HTML) Source Parser.

Parses Thrive Content Builder HTML into the universal element shape. TCB
markup is class-driven: ``tcb-flex-row``/``tcb-flex-col`` grids,
``tcb-button-block`` buttons, ``tve_image_caption`` images,
``tve-divider`` separators, ``thrv_responsive_spacer`` spacers, with
heading/paragraph tags carrying ``tve_*`` classes.
"""

from __future__ import annotations

from typing import Any, Dict, List, Optional

from translation_bridge.parsers.htmlbase import (
    HTMLSourceParser,
    UniversalElement,
    _classes,
    _node_text,
)
from translation_bridge.parsers.universal import (
    UniversalDocument,
    analyze_document,
    extract_document_content,
)
from translation_bridge.transforms.registry import ParserRegistry


@ParserRegistry.register(
    name="thrive_parser",
    framework="thrive",
    description="Thrive Architect TCB HTML parser",
    version="4.11.0",
    file_extensions=[".html"],
)
class ThriveParser(HTMLSourceParser):
    """Parse Thrive Architect HTML into a UniversalDocument."""

    framework = "thrive"
    button_class_hints = ("tcb-button", "tve_btn")

    def _walk(self, node, is_inner: bool) -> Optional[UniversalElement]:
        classes = _classes(node)

        if any("tcb-shortcode" in c for c in classes):
            return UniversalElement(
                id="", el_type="widget", widget_type="html",
                settings={"html": _node_text(node)}, is_inner=is_inner,
            )
        if "tcb-button-block" in classes:
            settings: Dict[str, Any] = {"text": _node_text(node)}
            href = self._find_attr(node, "a", "href")
            if href:
                settings["link"] = {"url": href}
            return UniversalElement(
                id="", el_type="widget", widget_type="button",
                settings=settings, is_inner=is_inner,
            )
        if "tve_image_caption" in classes:
            settings = {"image": {"url": self._find_attr(node, "img", "src") or ""}}
            alt = self._find_attr(node, "img", "alt")
            if alt:
                settings["image"]["alt"] = alt
            return UniversalElement(
                id="", el_type="widget", widget_type="image",
                settings=settings, is_inner=is_inner,
            )
        if "tve-divider" in classes:
            return UniversalElement(
                id="", el_type="widget", widget_type="divider",
                settings={}, is_inner=is_inner,
            )
        if "thrv_responsive_spacer" in classes:
            return UniversalElement(
                id="", el_type="widget", widget_type="spacer",
                settings={}, is_inner=is_inner,
            )

        return super()._walk(node, is_inner)

    @staticmethod
    def _find_attr(node, tag: str, attr: str) -> Optional[str]:
        if node.tag == tag and node.attrs.get(attr):
            return node.attrs[attr]
        for child in node.children:
            found = ThriveParser._find_attr(child, tag, attr)
            if found:
                return found
        return None

    # CLI surface -------------------------------------------------------

    def extract_content(self, doc: UniversalDocument) -> Dict[str, List[str]]:
        return extract_document_content(doc)

    def analyze(self, doc: UniversalDocument) -> Dict[str, Any]:
        return analyze_document(doc)

    def get_framework(self) -> str:
        return "thrive"
