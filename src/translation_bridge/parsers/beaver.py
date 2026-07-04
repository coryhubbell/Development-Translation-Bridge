"""
Translation Bridge v4 - Beaver Builder JSON Source Parser.

Parses Beaver Builder's flat node registry into the universal element shape.
Nodes are keyed (or listed) with ``{node, type, parent, position, settings}``;
hierarchy is Row > Column-Group > Column > Module via ``parent`` ids sorted
by ``position``.
"""

from __future__ import annotations

import json
from typing import Any, Dict, List, Optional, Union

from translation_bridge.parsers.universal import (
    UniversalDocument,
    UniversalElement,
    analyze_document,
    extract_document_content,
)
from translation_bridge.transforms.registry import ParserRegistry

_STRUCTURAL_TYPES = {
    "row": "section",
    "column-group": "container",
    "column": "column",
}

_MODULE_TYPES = {
    "heading": "heading",
    "rich-text": "text-editor",
    "photo": "image",
    "button": "button",
    "html": "html",
    "separator": "divider",
    "icon": "icon",
    "icon-group": "icon-list",
    "gallery": "image-gallery",
    "video": "video",
    "audio": "audio",
    "map": "google_maps",
    "slideshow": "slides",
    "slider": "slides",
    "content-slider": "slides",
    "testimonials": "testimonial",
    "callout": "call-to-action",
    "cta": "call-to-action",
    "contact-form": "form",
    "subscribe-form": "form",
    "login-form": "form",
    "menu": "nav",
    "accordion": "accordion",
    "tabs": "tabs",
    "pricing-table": "price-table",
    "countdown": "countdown",
    "number-counter": "counter",
    "posts": "posts",
}


@ParserRegistry.register(
    name="beaver_parser",
    framework="beaver-builder",
    description="Beaver Builder flat node-registry parser",
    version="4.11.0",
    file_extensions=[".json"],
)
class BeaverParser:
    """Parse Beaver Builder JSON into a UniversalDocument."""

    def parse_file(self, file_path: str) -> UniversalDocument:
        with open(file_path, "r", encoding="utf-8") as handle:
            return self.parse(json.load(handle))

    def parse(self, data: Union[str, Dict[str, Any], List[Any]]) -> UniversalDocument:
        if isinstance(data, str):
            data = json.loads(data)

        if isinstance(data, dict):
            nodes = [n for n in data.values() if isinstance(n, dict) and n.get("node")]
        elif isinstance(data, list):
            nodes = [n for n in data if isinstance(n, dict) and n.get("node")]
        else:
            return UniversalDocument()

        by_parent: Dict[str, List[Dict[str, Any]]] = {}
        for node in nodes:
            by_parent.setdefault(str(node.get("parent") or ""), []).append(node)
        for children in by_parent.values():
            children.sort(key=lambda n: float(n.get("position") or 0))

        roots = [
            el
            for node in by_parent.get("", [])
            if (el := self._parse_node(node, by_parent, False))
        ]
        return UniversalDocument(elements=roots, meta={"source_framework": "beaver-builder"})

    def _parse_node(
        self, node: Dict[str, Any], by_parent: Dict[str, List[Dict[str, Any]]], is_inner: bool
    ) -> Optional[UniversalElement]:
        node_type = str(node.get("type", ""))
        settings = node.get("settings") if isinstance(node.get("settings"), dict) else {}
        node_id = str(node.get("node", ""))

        if node_type in _STRUCTURAL_TYPES:
            element = UniversalElement(
                id=node_id,
                el_type=_STRUCTURAL_TYPES[node_type],
                settings={},
                is_inner=is_inner,
            )
        else:
            module_type = str(settings.get("type", node_type))
            widget_type = _MODULE_TYPES.get(module_type, "text-editor")
            element = UniversalElement(
                id=node_id,
                el_type="widget",
                widget_type=widget_type,
                settings=self._widget_settings(widget_type, settings),
                is_inner=is_inner,
            )

        for child in by_parent.get(node_id, []):
            child_el = self._parse_node(child, by_parent, True)
            if child_el:
                element.elements.append(child_el)
        return element

    def _widget_settings(self, widget_type: str, settings: Dict[str, Any]) -> Dict[str, Any]:
        out: Dict[str, Any] = {}

        if widget_type == "heading":
            out["title"] = str(settings.get("heading", ""))
            tag = str(settings.get("tag", "h2"))
            out["header_size"] = tag if tag.startswith("h") else "h2"
        elif widget_type == "text-editor":
            import re

            out["editor"] = re.sub(r"<[^>]+>", "", str(settings.get("text", ""))).strip()
        elif widget_type == "button":
            out["text"] = str(settings.get("text", ""))
            if settings.get("link"):
                out["link"] = {"url": str(settings["link"])}
                if str(settings.get("link_target", "")) == "_blank":
                    out["link"]["is_external"] = "on"
        elif widget_type == "image":
            out["image"] = {"url": str(settings.get("photo_src", settings.get("photo_url", "")))}
            if settings.get("alt"):
                out["image"]["alt"] = str(settings["alt"])
        elif widget_type == "html":
            out["html"] = str(settings.get("html", ""))
        elif widget_type == "testimonial":
            testimonials = settings.get("testimonials")
            first = testimonials[0] if isinstance(testimonials, list) and testimonials else {}
            if isinstance(first, dict):
                out["testimonial_content"] = str(first.get("testimonial", settings.get("text", "")))
            else:
                out["testimonial_content"] = str(settings.get("text", ""))
        elif widget_type == "call-to-action":
            out["title"] = str(settings.get("title", settings.get("heading", "")))
            out["description"] = str(settings.get("text", ""))
            if settings.get("btn_text"):
                out["button_text"] = str(settings["btn_text"])
        elif widget_type == "counter":
            out["title"] = str(settings.get("before_number_text", settings.get("text", "")))
            out["ending_number"] = str(settings.get("number", ""))
        elif settings.get("text"):
            out["text"] = str(settings["text"])

        return out

    # CLI surface -------------------------------------------------------

    def extract_content(self, doc: UniversalDocument) -> Dict[str, List[str]]:
        return extract_document_content(doc)

    def analyze(self, doc: UniversalDocument) -> Dict[str, Any]:
        return analyze_document(doc)

    def get_framework(self) -> str:
        return "beaver-builder"
