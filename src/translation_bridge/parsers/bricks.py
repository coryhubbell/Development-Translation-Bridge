"""
Translation Bridge v4 - Bricks Builder JSON Source Parser.

Parses Bricks Builder 2.x page JSON into the universal element shape so
Bricks content can ride the lossless ``transform`` path.

Real Bricks 2.x format (verified in the PHP implementation, v4.3.0): a FLAT
array of elements linked by ids::

    [
        {"id": "abc123", "name": "section", "parent": 0,
         "children": ["def456"], "settings": {...}},
        {"id": "def456", "name": "heading", "parent": "abc123",
         "children": [], "settings": {"text": "Hello", "tag": "h2"}}
    ]

Accepted input shapes:
- the bare flat list above,
- ``{"content": [...]}`` page exports,
- the legacy nested-children fixture shape (children as element objects).
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
from translation_bridge.responsive import bricks_settings_to_canonical
from translation_bridge.transforms.registry import ParserRegistry


# Bricks element name -> (elType, widgetType) in the universal shape.
_CONTAINER_TYPES = {
    "section": "section",
    "container": "container",
    "block": "container",
    "div": "column",
}

_WIDGET_TYPES = {
    "heading": "heading",
    "text": "text-editor",
    "text-basic": "text-editor",
    "rich-text": "text-editor",
    "button": "button",
    "image": "image",
    "video": "video",
    "divider": "divider",
    "icon": "icon",
    "icon-box": "icon-box",
    "list": "icon-list",
    "form": "form",
    "nav-menu": "nav",
    "nav-nested": "nav",
    "code": "html",
    "shortcode": "html",
    "map": "google_maps",
    "accordion": "accordion",
    "accordion-nested": "accordion",
    "tabs": "tabs",
    "tabs-nested": "tabs",
    "slider": "slides",
    "slider-nested": "slides",
    "carousel": "slides",
    "testimonials": "testimonial",
    "counter": "counter",
    "progress-bar": "progress",
    "image-gallery": "gallery",
    "posts": "posts",
    "pricing-tables": "price-table",
    "alert": "alert",
    "logo": "image",
    "svg": "icon",
}


@ParserRegistry.register(
    name="bricks_parser",
    framework="bricks",
    description="Bricks Builder 2.x flat page JSON parser",
    version="4.7.0",
    file_extensions=[".json"],
)
class BricksParser:
    """Parse Bricks Builder JSON into a UniversalDocument."""

    def parse_file(self, file_path: str) -> UniversalDocument:
        with open(file_path, "r", encoding="utf-8") as handle:
            return self.parse(json.load(handle))

    def parse(self, data: Union[str, Dict[str, Any], List[Any]]) -> UniversalDocument:
        if isinstance(data, str):
            data = json.loads(data)

        elements = self._extract_element_list(data)
        if not elements:
            return UniversalDocument()

        if self._is_flat(elements):
            roots = self._build_from_flat(elements)
        else:
            roots = [
                el
                for item in elements
                if isinstance(item, dict) and (el := self._parse_nested(item))
            ]

        return UniversalDocument(elements=roots, meta={"source_framework": "bricks"})

    # ------------------------------------------------------------------
    # Input-shape handling
    # ------------------------------------------------------------------

    def _extract_element_list(self, data: Any) -> List[Dict[str, Any]]:
        if isinstance(data, dict):
            for key in ("content", "elements"):
                if isinstance(data.get(key), list):
                    return [el for el in data[key] if isinstance(el, dict)]
            return []
        if isinstance(data, list):
            return [el for el in data if isinstance(el, dict)]
        return []

    def _is_flat(self, elements: List[Dict[str, Any]]) -> bool:
        """Real 2.x pages are flat: children are lists of id strings."""
        for element in elements:
            children = element.get("children")
            if isinstance(children, list) and any(isinstance(c, dict) for c in children):
                return False
        return True

    def _build_from_flat(self, elements: List[Dict[str, Any]]) -> List[UniversalElement]:
        by_id = {str(el.get("id")): el for el in elements if el.get("id") is not None}

        def build(element: Dict[str, Any], is_inner: bool) -> Optional[UniversalElement]:
            node = self._convert_element(element, is_inner)
            if node is None:
                return None
            for child_id in element.get("children") or []:
                child = by_id.get(str(child_id))
                if child is not None:
                    child_node = build(child, True)
                    if child_node:
                        node.elements.append(child_node)
            return node

        roots = []
        for element in elements:
            parent = element.get("parent", 0)
            if parent in (0, "0", "", None):
                root = build(element, False)
                if root:
                    roots.append(root)
        return roots

    def _parse_nested(self, element: Dict[str, Any], is_inner: bool = False) -> Optional[UniversalElement]:
        node = self._convert_element(element, is_inner)
        if node is None:
            return None
        for child in element.get("children") or []:
            if isinstance(child, dict):
                child_node = self._parse_nested(child, True)
                if child_node:
                    node.elements.append(child_node)
        return node

    # ------------------------------------------------------------------
    # Element normalization
    # ------------------------------------------------------------------

    def _convert_element(self, element: Dict[str, Any], is_inner: bool) -> Optional[UniversalElement]:
        name = str(element.get("name", ""))
        if not name:
            return None

        settings = element.get("settings") if isinstance(element.get("settings"), dict) else {}
        element_id = str(element.get("id", ""))

        # Canonicalize `:tablet_portrait` / `:mobile_portrait` setting-key
        # suffixes so responsive data survives cross-framework conversions.
        canonical = bricks_settings_to_canonical(settings)
        responsive = {"styles": canonical} if canonical else None

        if name in _CONTAINER_TYPES:
            return UniversalElement(
                id=element_id,
                el_type=_CONTAINER_TYPES[name],
                settings=self._container_settings(settings),
                is_inner=is_inner,
                responsive=responsive,
            )

        widget_type = _WIDGET_TYPES.get(name, "text-editor")
        return UniversalElement(
            id=element_id,
            el_type="widget",
            widget_type=widget_type,
            settings=self._widget_settings(name, settings),
            is_inner=is_inner,
            responsive=responsive,
        )

    def _container_settings(self, settings: Dict[str, Any]) -> Dict[str, Any]:
        out: Dict[str, Any] = {}
        background = settings.get("_background")
        if isinstance(background, dict) and background.get("color"):
            color = background["color"]
            out["background_color"] = color.get("hex", color) if isinstance(color, dict) else color
        return out

    def _widget_settings(self, name: str, settings: Dict[str, Any]) -> Dict[str, Any]:
        out: Dict[str, Any] = {}

        text = settings.get("text")

        if name == "heading":
            out["title"] = text or ""
            out["header_size"] = settings.get("tag", "h2")
        elif name in ("text", "text-basic", "rich-text"):
            out["editor"] = text or settings.get("content", "")
        elif name == "button":
            out["text"] = text or ""
            link = settings.get("link")
            if isinstance(link, dict) and link.get("url"):
                out["link"] = {"url": link["url"]}
                if link.get("newTab"):
                    out["link"]["is_external"] = "on"
        elif name in ("image", "logo"):
            image = settings.get("image")
            url = image.get("url", "") if isinstance(image, dict) else ""
            out["image"] = {"url": url}
            if isinstance(image, dict) and image.get("id"):
                out["image"]["id"] = image["id"]
            if settings.get("altText"):
                out["image"]["alt"] = settings["altText"]
        elif name == "video":
            out["youtube_url"] = settings.get("youTubeId", settings.get("url", ""))
        elif name in ("icon", "svg"):
            icon = settings.get("icon")
            if isinstance(icon, dict):
                out["selected_icon"] = {"value": icon.get("icon", "")}
        elif name in ("code", "shortcode"):
            out["html"] = settings.get("code", settings.get("shortcode", ""))
        elif name == "testimonials":
            items = settings.get("items")
            if isinstance(items, list) and items and isinstance(items[0], dict):
                first = items[0]
                out["testimonial_content"] = first.get("content", "")
                out["testimonial_name"] = first.get("name", "")
                out["testimonial_job"] = first.get("title", "")
        elif text:
            out["text"] = text

        return out

    # ------------------------------------------------------------------
    # CLI surface
    # ------------------------------------------------------------------

    def extract_content(self, doc: UniversalDocument) -> Dict[str, List[str]]:
        return extract_document_content(doc)

    def analyze(self, doc: UniversalDocument) -> Dict[str, Any]:
        return analyze_document(doc)

    def get_framework(self) -> str:
        return "bricks"
