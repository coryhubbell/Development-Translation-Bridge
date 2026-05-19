"""
Translation Bridge v4 - Elementor 4 Atomic Editor JSON Converter.

Elementor 4 replaces the v3 Section → Column → Widget hierarchy with semantic
atomic elements. Per Elementor's developer docs, each atomic node looks like::

    {
        "id": "12345678",
        "version": "0.0",
        "elType": "e-div-block",
        "isInner": false,
        "interactions": [],
        "settings": {},
        "editor_settings": {},
        "styles": {},
        "elements": []
    }

Confirmed atomic ``elType`` values:
    Layout:  e-div-block, e-flexbox, e-grid
    Widgets: e-heading, e-paragraph, e-button, e-image, e-form

The ``styles`` field is the structured, centralised location for local style
definitions (responsive variants and pseudo-state styles). v1 emits styles
verbatim under ``default.props`` so a follow-up patch can structure them
properly once a real Elementor 4 fixture is available.
"""

from __future__ import annotations

import json
import secrets
from typing import Any, Dict, List, Optional


# Upstream framework version this converter is calibrated against.
TARGET_CMS_VERSION: str = "4.0.0"

# Per-element schema version emitted on each atomic node.
ATOMIC_SCHEMA_VERSION: str = "0.0"


class Elementor4Converter:
    """Convert parsed/universal data to Elementor 4 Atomic Editor JSON."""

    ELEMENT_TYPE_MAP: Dict[str, str] = {
        "container": "e-div-block",
        "row": "e-flexbox",
        "column": "e-div-block",
        "text": "e-paragraph",
        "paragraph": "e-paragraph",
        "heading": "e-heading",
        "button": "e-button",
        "link": "e-button",
        "image": "e-image",
        "video": "e-video",
        "icon": "e-icon",
        "form": "e-form",
        "list": "e-list",
        # Fallbacks: atomic v4 has no dedicated widgets for these yet.
        "card": "e-div-block",
        "gallery": "e-div-block",
        "accordion": "e-div-block",
        "tabs": "e-div-block",
        "slider": "e-div-block",
        "divider": "e-div-block",
        "spacer": "e-div-block",
    }

    def convert(self, data: Any) -> str:
        """Convert parsed data to an atomic v4 JSON string."""
        return json.dumps(self.convert_to_list(data), indent=2)

    def convert_to_list(self, data: Any) -> List[Dict[str, Any]]:
        """Convert parsed data to a list of atomic v4 element dicts."""
        if isinstance(data, dict):
            elements = data.get("elements", [data])
        elif isinstance(data, list):
            elements = data
        else:
            return []

        out: List[Dict[str, Any]] = []
        for element in elements:
            if isinstance(element, dict):
                node = self._build_node(element, is_inner=False)
                if node:
                    out.append(node)
        return out

    def get_framework(self) -> str:
        return "elementor-4"

    def get_supported_types(self) -> List[str]:
        return list(self.ELEMENT_TYPE_MAP.keys())

    def _build_node(self, element: Dict[str, Any], is_inner: bool) -> Optional[Dict[str, Any]]:
        """Build a single atomic v4 node, recursing through children."""
        universal_type = (
            element.get("type")
            or element.get("widgetType")
            or self._normalize_el_type(element.get("elType"))
        )
        if not universal_type:
            return None

        el_type = self.ELEMENT_TYPE_MAP.get(universal_type)
        if el_type is None:
            return None

        settings = element.get("settings", element.get("attributes", {})) or {}
        content = element.get("content", "")

        node: Dict[str, Any] = {
            "id": self._generate_id(),
            "version": ATOMIC_SCHEMA_VERSION,
            "elType": el_type,
            "isInner": is_inner,
            "interactions": [],
            "settings": self._build_settings(el_type, settings, content),
            "editor_settings": {},
            "styles": self._build_styles(element),
            "elements": [],
        }

        child_source = element.get("children") or element.get("elements") or []
        for child in child_source:
            if isinstance(child, dict):
                child_node = self._build_node(child, is_inner=True)
                if child_node:
                    node["elements"].append(child_node)

        return node

    def _normalize_el_type(self, el_type: Optional[str]) -> Optional[str]:
        if not el_type:
            return None
        if el_type in self.ELEMENT_TYPE_MAP:
            return el_type
        if el_type == "section":
            return "container"
        if el_type == "widget":
            return None
        return el_type

    def _build_settings(
        self, el_type: str, settings: Dict[str, Any], content: str
    ) -> Dict[str, Any]:
        """Build the atomic v4 ``settings`` object for an element."""
        out: Dict[str, Any] = {}

        if el_type == "e-heading":
            out["title"] = content or settings.get("title", settings.get("text", ""))
            out["tag"] = settings.get("header_size", settings.get("tag", settings.get("level", "h2")))
        elif el_type in ("e-paragraph", "e-text"):
            out["text"] = content or settings.get("editor", settings.get("text", ""))
        elif el_type in ("e-button", "e-link"):
            out["text"] = content or settings.get("text", settings.get("label", ""))
            link = settings.get("link")
            if isinstance(link, dict):
                out["link"] = {
                    "url": link.get("url", ""),
                    "target": link.get("target", "_self"),
                }
            elif isinstance(link, str):
                out["link"] = {"url": link, "target": "_self"}
            elif "url" in settings:
                out["link"] = {"url": settings["url"], "target": settings.get("target", "_self")}
        elif el_type == "e-image":
            image = settings.get("image")
            if isinstance(image, dict):
                out["image"] = {"url": image.get("url", ""), "alt": image.get("alt", "")}
            elif "src" in settings:
                out["image"] = {
                    "url": settings.get("src", ""),
                    "alt": settings.get("alt", settings.get("alt_text", "")),
                }
        elif el_type == "e-icon":
            icon = settings.get("selected_icon") or settings.get("icon") or ""
            if isinstance(icon, dict):
                out["icon"] = icon.get("value", "")
            else:
                out["icon"] = icon
        else:
            if content:
                out["text"] = content

        # Pass through scalar settings we didn't explicitly handle.
        for key, value in settings.items():
            if key in out or key in ("link", "image", "selected_icon"):
                continue
            if isinstance(value, (str, int, float, bool)):
                out[key] = value

        return out

    def _build_styles(self, element: Dict[str, Any]) -> Dict[str, Any]:
        """Surface any universal styles as a single ``default`` style entry."""
        styles = element.get("styles") if isinstance(element.get("styles"), dict) else {}
        if not styles:
            return {}
        return {"default": {"props": styles}}

    def _generate_id(self) -> str:
        """Generate an 8-char hex id (matches Elementor's v3/v4 convention)."""
        return secrets.token_hex(4)
