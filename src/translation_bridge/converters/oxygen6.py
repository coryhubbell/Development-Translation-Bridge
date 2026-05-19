"""
Translation Bridge v4 - Oxygen 6 (Breakdance-proxy) JSON Converter.

Oxygen 6 is a ground-up rewrite of Oxygen Builder, built on the Breakdance
codebase (~80% shared). Classic Oxygen (``ct_*`` shortcode vocabulary, flat
``ct_parent`` linkage) is declared incompatible with Oxygen 6 by Oxygen's own
migration docs. This converter targets the new format described by upstream
research:

* Data lives in ``_breakdance_data`` post meta (or an Oxygen 6 alias).
* A **nested** JSON tree (not a flat parent-id table). Each node holds
  ``id``, ``type``, ``properties``, and a ``children`` list of nested nodes.
* Element ``type`` is namespaced, e.g. ``EssentialElements\\Heading``.
* A monotonic ``_nextNodeId`` counter avoids id collisions when injecting.

Until verified against a real Oxygen 6 export, the type prefix and property
key conventions are draft assumptions, isolated to the type map and
``_build_properties`` so a single patch can correct them.
"""

from __future__ import annotations

import json
from typing import Any, Dict, List, Optional


# Upstream framework version this converter is calibrated against.
TARGET_CMS_VERSION: str = "6.0.0"

# Namespaced element prefix used by Breakdance/Oxygen 6 (proxy).
ELEMENT_NAMESPACE: str = "EssentialElements\\"


class Oxygen6Converter:
    """Convert parsed/universal data to an Oxygen 6 JSON tree payload.

    The emitted payload shape::

        {
            "_version": 1,
            "_nextNodeId": <int>,
            "tree": [ <node>, ... ]
        }

    Each node::

        {
            "id": "n-1",
            "type": "EssentialElements\\Section",
            "properties": { ... },
            "children": [ <node>, ... ]
        }
    """

    # Local element name lookup (namespace prepended on emit). Keys are universal
    # types from the Translation Bridge component model; values are the local
    # Oxygen 6 element name.
    ELEMENT_TYPE_MAP: Dict[str, str] = {
        "container": "Section",
        "section": "Section",
        "row": "Section",
        "column": "Div",
        "heading": "Heading",
        "text": "Text",
        "paragraph": "Text",
        "image": "Image",
        "button": "Button",
        "link": "Link",
        "icon": "Icon",
        "video": "Video",
        "audio": "Audio",
        "divider": "Divider",
        "spacer": "Spacer",
        "list": "List",
        "accordion": "Accordion",
        "tabs": "Tabs",
        "form": "Form",
        "gallery": "Gallery",
        "slider": "Slider",
        "carousel": "Slider",
        "card": "Card",
        "icon-box": "Card",
        "testimonial": "Testimonial",
        "pricing-table": "Pricing",
        "counter": "Counter",
        "progress": "Progress",
        "map": "Map",
        "alert": "Alert",
        "social-icons": "SocialIcons",
        "countdown": "Countdown",
        "code": "Code",
        "nav": "Menu",
        "menu": "Menu",
    }

    def __init__(self) -> None:
        self._node_counter = 0

    def convert(self, data: Any) -> str:
        """Convert parsed data to an Oxygen 6 JSON string payload."""
        payload = self.convert_to_dict(data)
        return json.dumps(payload, indent=2)

    def convert_to_dict(self, data: Any) -> Dict[str, Any]:
        """Convert parsed data to an Oxygen 6 payload dict."""
        self._node_counter = 0

        tree = self._build_tree(data)
        return {
            "_version": 1,
            "_nextNodeId": self._node_counter + 1,
            "tree": tree,
        }

    def get_framework(self) -> str:
        return "oxygen-6"

    def get_supported_types(self) -> List[str]:
        return list(self.ELEMENT_TYPE_MAP.keys())

    def _build_tree(self, data: Any) -> List[Dict[str, Any]]:
        """Walk parsed/universal data and emit a list of root Oxygen 6 nodes."""
        if isinstance(data, dict):
            if "elements" in data and isinstance(data["elements"], list):
                return [n for el in data["elements"] if (n := self._build_node(el))]
            node = self._build_node(data)
            return [node] if node else []

        if isinstance(data, list):
            return [n for el in data if isinstance(el, dict) and (n := self._build_node(el))]

        return []

    def _build_node(self, element: Dict[str, Any]) -> Optional[Dict[str, Any]]:
        """Build a single Oxygen 6 node (and its subtree) from a parsed element."""
        if not isinstance(element, dict):
            return None

        # Derive the universal type from any of the common inbound shapes.
        universal_type = (
            element.get("type")
            or element.get("widgetType")
            or self._normalize_el_type(element.get("elType"))
        )
        if not universal_type:
            return None

        local_name = self.ELEMENT_TYPE_MAP.get(universal_type)
        if local_name is None:
            return None

        settings = element.get("settings", element.get("attributes", {})) or {}
        content = element.get("content", "")

        node: Dict[str, Any] = {
            "id": self._generate_id(),
            "type": ELEMENT_NAMESPACE + local_name,
            "properties": self._build_properties(local_name, settings, content),
            "children": [],
        }

        child_source = element.get("children")
        if not child_source:
            child_source = element.get("elements")
        if isinstance(child_source, list):
            for child in child_source:
                if isinstance(child, dict):
                    child_node = self._build_node(child)
                    if child_node:
                        node["children"].append(child_node)

        return node

    def _normalize_el_type(self, el_type: Optional[str]) -> Optional[str]:
        """Translate Elementor-style ``elType`` values to universal types."""
        if not el_type:
            return None
        if el_type in self.ELEMENT_TYPE_MAP:
            return el_type
        if el_type == "section":
            return "container"
        if el_type == "widget":
            return None  # widgetType handles this — caller falls through
        return el_type

    def _build_properties(
        self, local_name: str, settings: Dict[str, Any], content: str
    ) -> Dict[str, Any]:
        """Build the Oxygen 6 ``properties`` bag for an element.

        Content fields land at the top of the property bag (``text``, ``code``,
        ``src``...) mirroring the documented Oxygen 6 content/design split.
        Style-only data is grouped under ``design`` so the proxy round-trips
        without colliding with content keys.
        """
        properties: Dict[str, Any] = {}
        styles = settings.get("styles") or settings.get("_design") or {}

        if local_name == "Heading":
            properties["text"] = content or settings.get("title", settings.get("text", ""))
            properties["tag"] = settings.get("header_size", settings.get("tag", settings.get("level", "h2")))
        elif local_name in ("Text", "RichText"):
            properties["text"] = content or settings.get("editor", settings.get("text", ""))
        elif local_name in ("Button", "Link"):
            properties["text"] = content or settings.get("text", settings.get("label", ""))
            link = settings.get("link")
            if isinstance(link, dict):
                properties["link"] = {
                    "url": link.get("url", "#"),
                    "target": link.get("target", "_self"),
                }
            elif isinstance(link, str):
                properties["link"] = {"url": link, "target": "_self"}
            elif "url" in settings:
                properties["link"] = {"url": settings["url"], "target": settings.get("target", "_self")}
        elif local_name == "Image":
            image = settings.get("image")
            if isinstance(image, dict):
                properties["src"] = image.get("url", "")
                properties["alt"] = image.get("alt", "")
            else:
                properties["src"] = settings.get("src", settings.get("image_url", ""))
                properties["alt"] = settings.get("alt", settings.get("alt_text", ""))
        elif local_name == "Icon":
            icon = settings.get("selected_icon") or settings.get("icon") or ""
            if isinstance(icon, dict):
                properties["icon"] = icon.get("value", "")
            else:
                properties["icon"] = icon
        elif local_name == "Code":
            properties["code"] = content or settings.get("code", settings.get("html", ""))
        else:
            if content:
                properties["text"] = content

        # Pass through any extra scalar settings we didn't explicitly handle so
        # they aren't silently dropped (preserves round-trip fidelity).
        for key, value in settings.items():
            if key in properties or key in ("styles", "_design", "link", "image", "selected_icon"):
                continue
            if isinstance(value, (str, int, float, bool)):
                properties[key] = value

        if styles:
            properties["design"] = styles

        return properties

    def _generate_id(self) -> str:
        """Generate a deterministic node id ("n-1", "n-2", ...)."""
        self._node_counter += 1
        return f"n-{self._node_counter}"
