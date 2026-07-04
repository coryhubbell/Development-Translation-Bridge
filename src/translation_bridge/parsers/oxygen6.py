"""
Translation Bridge v4 - Oxygen 6 (Breakdance-based) JSON Source Parser.

Parses Oxygen 6 / Breakdance element trees into the universal element shape.
The node shape mirrors the PHP parser, verified against a real Breakdance
export (v4.4.0): integer ids, the element payload nested under ``data``,
``_parentId`` back-references, content fields under
``properties.content.content`` (heading tag key is the plural ``tags``),
and design data under ``properties.design`` with ``breakpoint_*`` leaves.

Accepted input shapes: the ``{"tree": {"root": {...}}, "_nextNodeId": N}``
envelope, the ``{"source": ..., "element": {...}}`` element-copy envelope,
bare nodes/lists, and the legacy flat proxy shape (type/properties at the
node top level).
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
from translation_bridge.responsive import oxygen6_design_to_canonical
from translation_bridge.transforms.registry import ParserRegistry


# Local element name (namespace stripped) -> (elType, widgetType).
_CONTAINER_TYPES = {
    "Section": "section",
    "Container": "container",
    "Columns": "container",
    "Column": "column",
    "Div": "column",
    "Block": "container",
    "PostsLoop": "container",
}

_WIDGET_TYPES = {
    "Heading": "heading",
    "Text": "text-editor",
    "RichText": "text-editor",
    "Paragraph": "text-editor",
    "Button": "button",
    "TextLink": "button",
    "Link": "button",
    "Image": "image",
    "Video": "video",
    "CodeBlock": "html",
    "Code": "html",
    "Icon": "icon",
    "IconBox": "icon-box",
    "TestimonialBox": "testimonial",
    "Testimonial": "testimonial",
    "PricingTable": "price-table",
    "Pricing": "price-table",
    "ProgressBar": "progress",
    "Progress": "progress",
    "Map": "google_maps",
    "Gallery": "gallery",
    "Menu": "nav",
    "NavMenu": "nav",
    "Tabs": "tabs",
    "Toggle": "accordion",
    "Accordion": "accordion",
    "Slider": "slides",
    "Divider": "divider",
    "Separator": "divider",
}


@ParserRegistry.register(
    name="oxygen6_parser",
    framework="oxygen6",
    description="Oxygen 6 / Breakdance JSON tree parser (verified node shape)",
    version="4.10.0",
    file_extensions=[".json"],
)
class Oxygen6Parser:
    """Parse Oxygen 6 / Breakdance JSON into a UniversalDocument."""

    def parse_file(self, file_path: str) -> UniversalDocument:
        with open(file_path, "r", encoding="utf-8") as handle:
            return self.parse(json.load(handle))

    def parse(self, data: Union[str, Dict[str, Any], List[Any]]) -> UniversalDocument:
        if isinstance(data, str):
            data = json.loads(data)

        nodes = self._extract_root_nodes(data)
        roots = [
            el
            for node in nodes
            if isinstance(node, dict) and (el := self._parse_node(node, False))
        ]
        return UniversalDocument(elements=roots, meta={"source_framework": "oxygen6"})

    # ------------------------------------------------------------------
    # Input-shape handling
    # ------------------------------------------------------------------

    def _extract_root_nodes(self, data: Any) -> List[Dict[str, Any]]:
        if isinstance(data, list):
            return [node for node in data if isinstance(node, dict)]
        if not isinstance(data, dict):
            return []

        tree = data.get("tree")
        if isinstance(tree, dict):
            root = tree.get("root")
            if isinstance(root, dict):
                children = root.get("children")
                if isinstance(children, list):
                    return [node for node in children if isinstance(node, dict)]
                return [root] if self._looks_like_node(root) else []
            return [tree] if self._looks_like_node(tree) else []
        if isinstance(tree, list):
            return [node for node in tree if isinstance(node, dict)]

        element = data.get("element")
        if isinstance(element, dict) and self._looks_like_node(element):
            return [element]

        if self._looks_like_node(data):
            return [data]

        return []

    @staticmethod
    def _looks_like_node(candidate: Dict[str, Any]) -> bool:
        return "type" in candidate or (
            isinstance(candidate.get("data"), dict) and "type" in candidate["data"]
        )

    # ------------------------------------------------------------------
    # Node normalization
    # ------------------------------------------------------------------

    def _parse_node(self, node: Dict[str, Any], is_inner: bool) -> Optional[UniversalElement]:
        data = node.get("data") if isinstance(node.get("data"), dict) else node
        type_full = str(data.get("type", ""))
        if not type_full or type_full == "root":
            return None

        local = type_full.rsplit("\\", 1)[-1]
        properties = data.get("properties") if isinstance(data.get("properties"), dict) else {}
        flat = self._flatten_properties(properties)
        element_id = str(node.get("id", ""))

        responsive = None
        design = properties.get("design")
        if isinstance(design, dict) and design:
            canonical = oxygen6_design_to_canonical(design)
            if canonical:
                responsive = {"styles": canonical}

        if local in _CONTAINER_TYPES:
            element = UniversalElement(
                id=element_id,
                el_type=_CONTAINER_TYPES[local],
                settings={},
                is_inner=is_inner,
                responsive=responsive,
            )
        else:
            widget_type = _WIDGET_TYPES.get(local, "text-editor")
            element = UniversalElement(
                id=element_id,
                el_type="widget",
                widget_type=widget_type,
                settings=self._widget_settings(widget_type, flat),
                is_inner=is_inner,
                responsive=responsive,
            )

        for child in node.get("children") or []:
            if isinstance(child, dict):
                child_element = self._parse_node(child, True)
                if child_element:
                    element.elements.append(child_element)

        return element

    @staticmethod
    def _flatten_properties(properties: Dict[str, Any]) -> Dict[str, Any]:
        """Merge the sectioned property bag into one flat content bag.

        Real exports group content under ``properties.content.<section>``;
        the legacy proxy shape carried fields at the top level.
        """
        flat: Dict[str, Any] = {}
        for key, value in properties.items():
            if key in ("content", "design", "settings", "meta"):
                continue
            flat[key] = value
        content = properties.get("content")
        if isinstance(content, dict):
            for section in content.values():
                if isinstance(section, dict):
                    flat.update(section)
        return flat

    def _widget_settings(self, widget_type: str, flat: Dict[str, Any]) -> Dict[str, Any]:
        out: Dict[str, Any] = {}

        if widget_type == "heading":
            out["title"] = str(flat.get("text", ""))
            tag = str(flat.get("tags", flat.get("tag", "h2")))
            out["header_size"] = tag if tag.startswith("h") else "h2"
        elif widget_type == "text-editor":
            out["editor"] = str(flat.get("text", ""))
        elif widget_type == "button":
            out["text"] = str(flat.get("text", ""))
            link = flat.get("link")
            if isinstance(link, dict) and link.get("url"):
                out["link"] = {"url": link["url"]}
                if link.get("target") == "_blank":
                    out["link"]["is_external"] = "on"
        elif widget_type == "image":
            image = flat.get("image")
            if isinstance(image, dict):
                out["image"] = {"url": image.get("url", flat.get("src", ""))}
            else:
                out["image"] = {"url": str(flat.get("src", ""))}
            if flat.get("alt"):
                out["image"]["alt"] = flat["alt"]
        elif widget_type == "html":
            out["html"] = str(flat.get("php_code", flat.get("code", flat.get("html", ""))))
        elif widget_type == "icon":
            icon = flat.get("icon")
            if icon:
                out["selected_icon"] = {"value": str(icon)}
        elif widget_type == "testimonial":
            out["testimonial_content"] = str(flat.get("text", flat.get("quote", "")))
            out["testimonial_name"] = str(flat.get("name", flat.get("author", "")))
            out["testimonial_job"] = str(flat.get("title", "")) if flat.get("name") or flat.get("author") else ""
        elif flat.get("text"):
            out["text"] = str(flat["text"])

        return out

    # ------------------------------------------------------------------
    # CLI surface
    # ------------------------------------------------------------------

    def extract_content(self, doc: UniversalDocument) -> Dict[str, List[str]]:
        return extract_document_content(doc)

    def analyze(self, doc: UniversalDocument) -> Dict[str, Any]:
        return analyze_document(doc)

    def get_framework(self) -> str:
        return "oxygen6"
