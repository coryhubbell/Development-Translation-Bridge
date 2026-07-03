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

Verified against the open-source ``elementor/elementor`` repository
(``modules/atomic-widgets``). Real atomic ``elType`` values
(``get_element_type()`` in ``modules/atomic-widgets/elements/*``):
    Layout:  e-div-block, e-flexbox, e-grid
    Widgets: e-heading, e-paragraph, e-button, e-image, e-svg, e-divider,
             e-youtube, e-self-hosted-video, e-form

Settings use Elementor's typed-prop system — every stored value is wrapped in
a ``{"$$type": <prop-type-key>, "value": ...}`` envelope:

- ``e-heading``:   ``title`` (``html-v3``), ``tag`` (``string``)
- ``e-paragraph``: ``paragraph`` (``html-v3``), ``tag`` (``string``)
- ``e-button``:    ``text`` (``html-v3``), ``link`` (``link``)
- ``e-image``:     ``image`` (``image`` → ``src``/``size``)

``html-v3`` values nest a string prop: ``{"content": {"$$type": "string",
"value": ...}}`` (see ``prop-types/html-v3-prop-type.php``). Links store
``destination`` (url prop) + ``isTargetBlank`` (boolean prop), not
``url``/``target`` (see ``prop-types/link-prop-type.php``).

``styles`` entries follow ``Style_Definition::build()``:
``{id, type, label, variants: [{meta: {breakpoint, state}, props}]}``.
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
        "video": "e-youtube",
        "icon": "e-svg",
        "form": "e-form",
        "divider": "e-divider",
        # Fallbacks: atomic v4 has no dedicated widgets for these yet.
        "list": "e-div-block",
        "card": "e-div-block",
        "gallery": "e-div-block",
        "accordion": "e-div-block",
        "tabs": "e-div-block",
        "slider": "e-div-block",
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

        styles = self._build_styles(element)
        node_settings = self._build_settings(el_type, settings, content)
        if styles:
            # Local styles apply through the classes prop referencing the
            # style definition id (see atomic-widgets/styles).
            node_settings["classes"] = self._prop("classes", list(styles.keys()))

        node: Dict[str, Any] = {
            "id": self._generate_id(),
            "version": ATOMIC_SCHEMA_VERSION,
            "elType": el_type,
            "isInner": is_inner,
            "interactions": [],
            "settings": node_settings,
            "editor_settings": {},
            "styles": styles,
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

    # ------------------------------------------------------------------
    # Typed-prop wrappers (Elementor atomic prop system).
    # ------------------------------------------------------------------

    @staticmethod
    def _prop(prop_type: str, value: Any) -> Dict[str, Any]:
        return {"$$type": prop_type, "value": value}

    def _string_prop(self, value: str) -> Dict[str, Any]:
        return self._prop("string", value)

    def _html_prop(self, value: str) -> Dict[str, Any]:
        """``html-v3``: content nests a string prop (Html_V3_Prop_Type)."""
        return self._prop("html-v3", {"content": self._string_prop(value)})

    def _link_prop(self, url: str, target: str = "_self") -> Dict[str, Any]:
        """``link``: destination url prop + isTargetBlank boolean prop."""
        return self._prop(
            "link",
            {
                "destination": self._prop("url", url),
                "isTargetBlank": self._prop("boolean", target == "_blank"),
            },
        )

    def _image_prop(self, url: str, alt: str = "") -> Dict[str, Any]:
        """``image``: src (image-src → id/url/alt) + size (Image_Prop_Type)."""
        src: Dict[str, Any] = {"id": None, "url": self._prop("url", url)}
        if alt:
            src["alt"] = self._string_prop(alt)
        return self._prop(
            "image",
            {
                "src": self._prop("image-src", src),
                "size": self._string_prop("full"),
            },
        )

    def _wrap_scalar(self, value: Any) -> Any:
        if isinstance(value, bool):
            return self._prop("boolean", value)
        if isinstance(value, (int, float)):
            return self._prop("number", value)
        if isinstance(value, str):
            return self._string_prop(value)
        return value

    def _build_settings(
        self, el_type: str, settings: Dict[str, Any], content: str
    ) -> Dict[str, Any]:
        """Build the atomic v4 ``settings`` object using typed props."""
        out: Dict[str, Any] = {}

        if el_type == "e-heading":
            title = content or settings.get("title", settings.get("text", ""))
            out["title"] = self._html_prop(title)
            tag = settings.get("header_size", settings.get("tag", settings.get("level", "h2")))
            out["tag"] = self._string_prop(tag)
        elif el_type == "e-paragraph":
            text = content or settings.get("editor", settings.get("text", ""))
            out["paragraph"] = self._html_prop(text)
        elif el_type == "e-button":
            out["text"] = self._html_prop(content or settings.get("text", settings.get("label", "")))
            link = settings.get("link")
            if isinstance(link, dict):
                out["link"] = self._link_prop(link.get("url", ""), link.get("target", "_self"))
            elif isinstance(link, str):
                out["link"] = self._link_prop(link)
            elif "url" in settings:
                out["link"] = self._link_prop(settings["url"], settings.get("target", "_self"))
        elif el_type == "e-image":
            image = settings.get("image")
            if isinstance(image, dict):
                out["image"] = self._image_prop(image.get("url", ""), image.get("alt", ""))
            elif "src" in settings:
                out["image"] = self._image_prop(
                    settings.get("src", ""),
                    settings.get("alt", settings.get("alt_text", "")),
                )
        elif el_type == "e-svg" and (settings.get("selected_icon") or settings.get("icon")):
            icon = settings.get("selected_icon") or settings.get("icon") or ""
            if isinstance(icon, dict):
                icon = icon.get("value", "")
            out["svg"] = self._string_prop(icon)
        elif content:
            out["paragraph"] = self._html_prop(content)

        # Pass through scalar settings we didn't explicitly handle, wrapped.
        for key, value in settings.items():
            if key in out or key in ("link", "image", "selected_icon", "title", "text", "editor"):
                continue
            if isinstance(value, (str, int, float, bool)):
                out[key] = self._wrap_scalar(value)

        return out

    def _build_styles(self, element: Dict[str, Any]) -> Dict[str, Any]:
        """Emit styles as a real Style_Definition entry with variants."""
        styles = element.get("styles") if isinstance(element.get("styles"), dict) else {}
        if not styles:
            return {}
        style_id = f"e-{self._generate_id()}"
        return {
            style_id: {
                "id": style_id,
                "type": "class",
                "label": "local",
                "variants": [
                    {
                        "meta": {"breakpoint": "desktop", "state": None},
                        "props": styles,
                    }
                ],
            }
        }

    def _generate_id(self) -> str:
        """Generate an 8-char hex id (matches Elementor's v3/v4 convention)."""
        return secrets.token_hex(4)
