"""
Translation Bridge v4 - DIVI 5 Block Converter.

DIVI 5 abandoned the ``[et_pb_*]`` shortcode track for the WordPress block
serialization spec under the ``divi`` namespace. Pages look like::

    <!-- wp:divi/section {"module":{...},"builderVersion":"5.0.0"} -->
      <!-- wp:divi/row {...} -->
        <!-- wp:divi/column {...} -->
          <!-- wp:divi/text {"module":{"content":{"innerContent":{"desktop":{"value":"..."}}}}} /-->
        <!-- /wp:divi/column -->
      <!-- /wp:divi/row -->
    <!-- /wp:divi/section -->

Container blocks emit opening/closing pairs; leaf blocks self-close. Content
values are wrapped in DIVI 5's responsive desktop variant; tablet/phone
overrides are not synthesised in v1.
"""

from __future__ import annotations

import json
from typing import Any, Dict, List, Optional


# Upstream framework version this converter is calibrated against.
TARGET_CMS_VERSION: str = "5.0.0"

# Block namespace prefix used by DIVI 5.
BLOCK_NAMESPACE: str = "divi/"


class Divi5Converter:
    """Convert parsed/universal data to DIVI 5 block-comment markup."""

    # Universal type → DIVI 5 local block name (namespace prepended on emit).
    ELEMENT_TYPE_MAP: Dict[str, str] = {
        "container": "section",
        "section": "section",
        "row": "row",
        "column": "column",
        "text": "text",
        "paragraph": "text",
        "heading": "heading",
        "button": "button",
        "image": "image",
        "video": "video",
        "audio": "audio",
        "gallery": "gallery",
        "divider": "divider",
        "card": "blurb",
        "testimonial": "testimonial",
        "accordion": "accordion",
        "tabs": "tabs",
        "slider": "slider",
        "code": "code",
        "pricing-table": "pricing-table",
        "counter": "counter",
        "progress": "progress",
        "social-icons": "social-media",
        "cta": "cta",
        "form": "contact-form",
        "nav": "menu",
        "icon": "icon",
        "map": "map",
        "countdown": "countdown",
    }

    # Leaf modules emit a self-closing block when they have no actual children.
    SELF_CLOSING = {
        "text", "heading", "button", "image", "video", "audio", "divider",
        "code", "counter", "progress", "icon", "map", "countdown", "cta",
        "blurb", "testimonial", "gallery", "contact-form", "pricing-table",
        "social-media", "menu",
    }

    def convert(self, data: Any) -> str:
        """Convert parsed data to a DIVI 5 block-markup string."""
        if isinstance(data, dict):
            elements = data.get("elements", [data])
        elif isinstance(data, list):
            elements = data
        else:
            return ""

        out: List[str] = []
        for element in elements:
            if isinstance(element, dict):
                rendered = self._render_element(element)
                if rendered:
                    out.append(rendered)
        return "".join(out)

    def get_framework(self) -> str:
        return "divi-5"

    def get_supported_types(self) -> List[str]:
        return list(self.ELEMENT_TYPE_MAP.keys())

    def _render_element(self, element: Dict[str, Any]) -> str:
        """Render a single element (and its children) into block markup."""
        universal_type = (
            element.get("type")
            or element.get("widgetType")
            or self._normalize_el_type(element.get("elType"))
        )
        if not universal_type:
            return ""

        local = self.ELEMENT_TYPE_MAP.get(universal_type)
        if local is None:
            return ""

        attrs = self._build_attrs(local, element)
        attrs_json = json.dumps(attrs, separators=(",", ":"))

        child_source = element.get("children") or element.get("elements") or []
        rendered_children = "".join(
            self._render_element(c) for c in child_source if isinstance(c, dict)
        )

        if local in self.SELF_CLOSING and not rendered_children:
            return f"<!-- wp:{BLOCK_NAMESPACE}{local} {attrs_json} /-->\n"

        return (
            f"<!-- wp:{BLOCK_NAMESPACE}{local} {attrs_json} -->\n"
            f"{rendered_children}"
            f"<!-- /wp:{BLOCK_NAMESPACE}{local} -->\n"
        )

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

    def _build_attrs(self, local: str, element: Dict[str, Any]) -> Dict[str, Any]:
        """Build a DIVI 5 block attribute object."""
        settings = element.get("settings", element.get("attributes", {})) or {}
        content_text = element.get("content", "")

        module_content: Dict[str, Any] = {}

        if local == "text":
            text = content_text or settings.get("editor", settings.get("text", ""))
            if text:
                module_content["innerContent"] = self._responsive(text)
        elif local == "heading":
            text = content_text or settings.get("title", settings.get("text", ""))
            if text:
                module_content["text"] = self._responsive(text)
            level = settings.get("header_size", settings.get("level", settings.get("tag", "h2")))
            module_content["level"] = self._responsive(level)
        elif local == "button":
            text = content_text or settings.get("text", settings.get("label", ""))
            if text:
                module_content["text"] = self._responsive(text)
            link = settings.get("link")
            url = ""
            new_window = False
            if isinstance(link, dict):
                url = link.get("url", "")
                new_window = bool(link.get("target") == "_blank" or link.get("urlNewWindow"))
            elif isinstance(link, str):
                url = link
            elif "url" in settings:
                url = settings["url"]
            if url:
                module_content["url"] = self._responsive(url)
            if new_window or settings.get("target") == "_blank":
                module_content["urlNewWindow"] = True
        elif local == "image":
            image = settings.get("image")
            src = ""
            alt = ""
            if isinstance(image, dict):
                src = image.get("url", "")
                alt = image.get("alt", "")
            else:
                src = settings.get("src", settings.get("image_url", ""))
                alt = settings.get("alt", settings.get("alt_text", ""))
            if src:
                module_content["src"] = self._responsive(src)
            if alt:
                module_content["alt"] = self._responsive(alt)
        elif local == "code":
            code = content_text or settings.get("code", settings.get("html", ""))
            if code:
                module_content["code"] = self._responsive(code)
        else:
            if content_text:
                module_content["text"] = self._responsive(content_text)

        attrs: Dict[str, Any] = {}
        if module_content:
            attrs["module"] = {"content": module_content}
        attrs["builderVersion"] = TARGET_CMS_VERSION
        return attrs

    def _responsive(self, value: Any) -> Dict[str, Dict[str, Any]]:
        """Wrap a scalar in DIVI 5's desktop responsive variant."""
        return {"desktop": {"value": value}}
