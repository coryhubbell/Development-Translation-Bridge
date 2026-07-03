"""
Translation Bridge v4 - Oxygen 6 (Breakdance-based) JSON Converter.

Oxygen 6 is a ground-up rewrite of Oxygen Builder, built on the Breakdance
codebase (~80% shared). Classic Oxygen (``ct_*`` shortcode vocabulary, flat
``ct_parent`` linkage) is declared incompatible with Oxygen 6 by Oxygen's own
migration docs.

The node shape below is **verified against a real Breakdance export**
(element-copy JSON from a production site):

* Data lives in ``_breakdance_data`` post meta (or an Oxygen 6 alias).
* Node ids are integers; children carry a ``_parentId`` back-reference.
* Each node nests its payload under ``data``::

      {
          "id": 102,
          "data": {
              "type": "EssentialElements\\Heading",
              "properties": {
                  "content": {"content": {"text": "Genre: ", "tags": "h4"}},
                  "design": { ... },
                  "meta": {"friendlyName": "Filters"}
              }
          },
          "children": [ ... ],
          "_parentId": 101
      }

* ``properties`` groups into ``content`` / ``design`` / ``settings`` / ``meta``
  sections; content fields nest one level deeper under ``content.content``
  (e.g. heading text at ``properties.content.content.text`` with the tag under
  ``tags`` — plural).
* A monotonic ``_nextNodeId`` counter avoids id collisions when injecting.

The full-document envelope (``tree`` wrapping a ``root`` node) follows the
documented ``_breakdance_data`` conventions; the parser accepts both this and
a bare node list.
"""

from __future__ import annotations

import json
from typing import Any, Dict, List, Optional

from translation_bridge.responsive import (
    canonical_to_oxygen6_design,
    element_responsive,
)


# Upstream framework version this converter is calibrated against.
TARGET_CMS_VERSION: str = "6.0.0"

# Namespaced element prefix used by Breakdance/Oxygen 6 (verified against a
# real Breakdance export).
ELEMENT_NAMESPACE: str = "EssentialElements\\"


class Oxygen6Converter:
    """Convert parsed/universal data to an Oxygen 6 JSON tree payload.

    The emitted payload shape::

        {
            "tree": {
                "root": {
                    "id": 1,
                    "data": {"type": "root", "properties": []},
                    "children": [ <node>, ... ]
                }
            },
            "_nextNodeId": <int>
        }
    """

    # Local element name lookup (namespace prepended on emit). Keys are universal
    # types from the Translation Bridge component model; values are the local
    # Oxygen 6 element name. Div / Heading / CodeBlock verified against a real
    # export; Section / Columns / Column / Text / Button / Image match the
    # published Breakdance element registry.
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
        "link": "TextLink",
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
        "pricing-table": "PricingTable",
        "counter": "Counter",
        "progress": "ProgressBar",
        "map": "Map",
        "alert": "Alert",
        "social-icons": "SocialIcons",
        "countdown": "Countdown",
        "code": "CodeBlock",
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
        self._node_counter = 1  # id 1 is reserved for the root node

        children = self._build_tree(data, parent_id=1)
        return {
            "tree": {
                "root": {
                    "id": 1,
                    "data": {"type": "root", "properties": []},
                    "children": children,
                }
            },
            "_nextNodeId": self._node_counter + 1,
        }

    def get_framework(self) -> str:
        return "oxygen-6"

    def get_supported_types(self) -> List[str]:
        return list(self.ELEMENT_TYPE_MAP.keys())

    def _build_tree(self, data: Any, parent_id: int) -> List[Dict[str, Any]]:
        """Walk parsed/universal data and emit a list of Oxygen 6 nodes."""
        if isinstance(data, dict):
            if "elements" in data and isinstance(data["elements"], list):
                return [
                    n
                    for el in data["elements"]
                    if (n := self._build_node(el, parent_id))
                ]
            node = self._build_node(data, parent_id)
            return [node] if node else []

        if isinstance(data, list):
            return [
                n
                for el in data
                if isinstance(el, dict) and (n := self._build_node(el, parent_id))
            ]

        return []

    def _build_node(
        self, element: Dict[str, Any], parent_id: int
    ) -> Optional[Dict[str, Any]]:
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

        node_id = self._generate_id()
        node: Dict[str, Any] = {
            "id": node_id,
            "data": {
                "type": ELEMENT_NAMESPACE + local_name,
                "properties": self._build_properties(local_name, settings, content, element),
            },
            "children": [],
            "_parentId": parent_id,
        }

        child_source = element.get("children")
        if not child_source:
            child_source = element.get("elements")
        if isinstance(child_source, list):
            for child in child_source:
                if isinstance(child, dict):
                    child_node = self._build_node(child, node_id)
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
        self,
        local_name: str,
        settings: Dict[str, Any],
        content: str,
        element: Dict[str, Any],
    ) -> Dict[str, Any]:
        """Build the Oxygen 6 ``properties`` bag for an element.

        Verified against a real Breakdance export: content fields nest under
        ``content.content`` (a section named ``content`` inside the content
        tab), design data under ``design``, and the admin label under
        ``meta.friendlyName``. Heading tags use the plural ``tags`` key.
        """
        properties: Dict[str, Any] = {}
        content_fields: Dict[str, Any] = {}
        styles = settings.get("styles") or settings.get("_design") or {}

        if local_name == "Heading":
            content_fields["text"] = content or settings.get("title", settings.get("text", ""))
            content_fields["tags"] = settings.get(
                "header_size", settings.get("tag", settings.get("level", "h2"))
            )
        elif local_name in ("Text", "RichText"):
            content_fields["text"] = content or settings.get("editor", settings.get("text", ""))
        elif local_name in ("Button", "TextLink"):
            content_fields["text"] = content or settings.get("text", settings.get("label", ""))
            link = settings.get("link")
            if isinstance(link, dict):
                content_fields["link"] = {
                    "url": link.get("url", "#"),
                    "target": link.get("target", "_self"),
                }
            elif isinstance(link, str):
                content_fields["link"] = {"url": link, "target": "_self"}
            elif "url" in settings:
                content_fields["link"] = {
                    "url": settings["url"],
                    "target": settings.get("target", "_self"),
                }
        elif local_name == "Image":
            image = settings.get("image")
            if isinstance(image, dict):
                content_fields["src"] = image.get("url", "")
                content_fields["alt"] = image.get("alt", "")
            else:
                content_fields["src"] = settings.get("src", settings.get("image_url", ""))
                content_fields["alt"] = settings.get("alt", settings.get("alt_text", ""))
        elif local_name == "Icon":
            icon = settings.get("selected_icon") or settings.get("icon") or ""
            if isinstance(icon, dict):
                icon = icon.get("value", "")
            content_fields["icon"] = icon
        elif local_name == "CodeBlock":
            # Real CodeBlock exports carry php_code / javascript_code fields;
            # php_code accepts raw HTML too.
            content_fields["php_code"] = content or settings.get("code", settings.get("html", ""))
        elif content:
            content_fields["text"] = content

        # Pass through any extra scalar settings we didn't explicitly handle so
        # they aren't silently dropped (preserves round-trip fidelity).
        for key, value in settings.items():
            if key in content_fields or key in ("styles", "_design", "link", "image", "selected_icon"):
                continue
            if isinstance(value, (str, int, float, bool)):
                content_fields[key] = value

        if content_fields:
            properties["content"] = {"content": content_fields}

        # Responsive round-trip: rebuild the design tree with breakpoint_*
        # leaves from canonical responsive data when present; otherwise fall
        # back to the flat styles bag.
        responsive = element_responsive(element) or {}
        canonical = responsive.get("styles")
        if isinstance(canonical, dict):
            design = canonical_to_oxygen6_design(canonical)
            if design:
                properties["design"] = design
        elif styles:
            properties["design"] = styles

        return properties

    def _generate_id(self) -> int:
        """Generate a monotonically increasing integer node id."""
        self._node_counter += 1
        return self._node_counter
