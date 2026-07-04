"""
Translation Bridge v4 - DIVI 5 Block Converter.

DIVI 5 abandoned the ``[et_pb_*]`` shortcode track for the WordPress block
serialization spec under the ``divi`` namespace. Pages look like::

    <!-- wp:divi/section {"builderVersion":"5.0.0"} -->
      <!-- wp:divi/row {...} -->
        <!-- wp:divi/column {...} -->
          <!-- wp:divi/text {"content":{"innerContent":{"desktop":{"value":"..."}}},"builderVersion":"5.0.0"} /-->
        <!-- /wp:divi/column -->
      <!-- /wp:divi/row -->
    <!-- /wp:divi/section -->

Container blocks emit opening/closing pairs; leaf blocks self-close. Content
values are wrapped in DIVI 5's responsive desktop variant by default; when an
inbound element carries canonical responsive data (``element["responsive"]``
or ``element["metadata"]["responsive"]``, see
``translation_bridge.responsive``), full multi-breakpoint wrappers with
tablet/phone overrides and hover states are emitted instead.
"""

from __future__ import annotations

import json
from typing import Any, Dict, List, Optional

from translation_bridge.responsive import (
    canonical_to_divi5_wrapper,
    element_responsive,
)


# Upstream framework version this converter is calibrated against.
TARGET_CMS_VERSION: str = "5.0.0"

# Block namespace prefix used by DIVI 5.
BLOCK_NAMESPACE: str = "divi/"


# Content-bearing settings keys (fidelity convention shared with cli.py /
# transforms: the exclusion list wins so keys like title_color never count).
_CONTENT_KEY_PARTS = (
    "text", "title", "content", "description", "heading", "editor",
    "caption", "label", "alt", "html", "name", "job", "address", "url", "date",
)
_NON_CONTENT_KEY_PARTS = (
    "color", "size", "typography", "weight", "align", "style", "position",
    "gap", "width", "height", "radius", "spacing", "margin", "padding",
    "font", "shadow", "border", "opacity", "hover",
)


def _is_content_key(key: str) -> bool:
    key_lower = key.lower()
    if any(part in key_lower for part in _NON_CONTENT_KEY_PARTS):
        return False
    return any(part in key_lower for part in _CONTENT_KEY_PARTS)


def _content_setting_strings(settings: Dict[str, Any]) -> List[str]:
    """Every content-bearing string in a canonical settings dict (one level
    of dict nesting and list items, matching the fidelity collector)."""
    out: List[str] = []
    for key, value in settings.items():
        if isinstance(value, str):
            if value.strip() and _is_content_key(key):
                out.append(value.strip())
        elif isinstance(value, dict):
            for sub_key, sub in value.items():
                if isinstance(sub, str) and sub.strip() and _is_content_key(sub_key):
                    out.append(sub.strip())
        elif isinstance(value, list):
            for item in value:
                if isinstance(item, dict):
                    for sub_key, sub in item.items():
                        if isinstance(sub, str) and sub.strip() and _is_content_key(sub_key):
                            out.append(sub.strip())
    return out


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

    # Canonical universal widgetType → DIVI 5 local block name (universal
    # documents, schema/universal-element.schema.json). Unlisted widget types
    # fall back to a content-preserving text block.
    UNIVERSAL_WIDGET_MAP: Dict[str, str] = {
        "heading": "heading",
        "text-editor": "text",
        "text": "text",
        "paragraph": "text",
        "button": "button",
        "image": "image",
        "video": "video",
        "audio": "audio",
        "icon": "icon",
        "icon-box": "blurb",
        "icon-list": "text",
        "image-gallery": "gallery",
        "testimonial": "testimonial",
        "blockquote": "testimonial",
        "call-to-action": "cta",
        "price-table": "pricing-table",
        "alert": "text",
        "tabs": "tabs",
        "accordion": "accordion",
        "slides": "slider",
        "slider": "slider",
        "counter": "counter",
        "progress": "progress",
        "countdown": "countdown",
        "form": "contact-form",
        "nav": "menu",
        "nav-menu": "menu",
        "google_maps": "map",
        "social-icons": "social-media",
        "divider": "divider",
        "spacer": "divider",
        "html": "code",
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
        # Universal documents (elType/widgetType, canonical settings) take the
        # universal path; component-shaped dicts ("type") keep the legacy one.
        if "type" not in element and (
            element.get("elType") in ("section", "container", "row", "column", "widget")
            or element.get("widgetType")
        ):
            return self._render_universal(element)

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
        attrs_json = self._serialize_attrs(attrs)

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

        self._overlay_responsive(element, module_content)

        attrs: Dict[str, Any] = {}
        if module_content:
            # Verified against the Divi 5 block-format docs: content lives in
            # a TOP-LEVEL "content" attribute group (attrs.content.innerContent
            # etc.), not nested under "module" — module holds meta/decoration.
            attrs["content"] = module_content
        attrs["builderVersion"] = TARGET_CMS_VERSION
        return attrs

    def _overlay_responsive(self, element: Dict[str, Any], module_content: Dict[str, Any]) -> None:
        """Round-trip responsive data: overlay full multi-breakpoint wrappers
        for any field with canonical responsive data (tablet/phone
        breakpoints, hover states)."""
        responsive = element_responsive(element) or {}
        for field, canonical in (responsive.get("fields") or {}).items():
            if not isinstance(canonical, dict):
                continue
            wrapper = canonical_to_divi5_wrapper(canonical)
            if not wrapper:
                continue
            existing = module_content.get(field, {})
            if "value" not in wrapper.get("desktop", {}) and isinstance(existing, dict):
                desktop_default = existing.get("desktop", {}).get("value")
                if desktop_default is not None:
                    wrapper.setdefault("desktop", {})["value"] = desktop_default
            module_content[field] = wrapper

    # ------------------------------------------------------------------
    # Universal-document path (RFC 5.0): elements shaped by
    # schema/universal-element.schema.json with the canonical settings
    # vocabulary, regardless of which source parser produced them.
    # ------------------------------------------------------------------

    def _render_universal(self, element: Dict[str, Any]) -> str:
        el_type = element.get("elType")
        if el_type == "widget" or (element.get("widgetType") and not el_type):
            return self._render_universal_widget(element)
        if el_type == "section":
            return self._render_universal_section(element)
        if el_type in ("container", "row"):
            return self._render_universal_row(element)
        if el_type == "column":
            return self._render_universal_column(element)
        # Unknown structural shape — recurse so children never drop.
        return "".join(
            self._render_universal(c)
            for c in element.get("elements") or []
            if isinstance(c, dict)
        )

    def _universal_children(self, element: Dict[str, Any]) -> List[Dict[str, Any]]:
        return [c for c in element.get("elements") or [] if isinstance(c, dict)]

    def _render_universal_section(self, element: Dict[str, Any]) -> str:
        """Sections hold rows. Nested containers each become a row; loose
        columns are grouped into one row; bare widgets get a default column."""
        rows: List[str] = []
        columns: List[str] = []
        modules: List[str] = []
        for child in self._universal_children(element):
            child_type = child.get("elType")
            if child_type in ("section", "container", "row"):
                rows.append(self._render_universal_row(child))
            elif child_type == "column":
                columns.append(self._render_universal_column(child))
            else:
                modules.append(self._render_universal(child))
        if modules:
            columns.append(self._structural_block("column", "".join(modules)))
        if columns:
            rows.append(self._structural_block("row", "".join(columns)))
        return self._structural_block("section", "".join(rows))

    def _render_universal_row(self, element: Dict[str, Any]) -> str:
        """Rows hold columns. Bare widgets and nested containers share a
        default column so their content always survives."""
        columns: List[str] = []
        modules: List[str] = []
        for child in self._universal_children(element):
            child_type = child.get("elType")
            if child_type == "column":
                columns.append(self._render_universal_column(child))
            elif child_type in ("section", "container", "row"):
                modules.append(self._render_universal_row(child))
            else:
                modules.append(self._render_universal(child))
        if modules:
            columns.append(self._structural_block("column", "".join(modules)))
        return self._structural_block("row", "".join(columns))

    def _render_universal_column(self, element: Dict[str, Any]) -> str:
        inner = "".join(
            self._render_universal(child) for child in self._universal_children(element)
        )
        return self._structural_block("column", inner)

    def _structural_block(self, local: str, inner: str) -> str:
        attrs_json = self._serialize_attrs({"builderVersion": TARGET_CMS_VERSION})
        return (
            f"<!-- wp:{BLOCK_NAMESPACE}{local} {attrs_json} -->\n"
            f"{inner}"
            f"<!-- /wp:{BLOCK_NAMESPACE}{local} -->\n"
        )

    def _render_universal_widget(self, element: Dict[str, Any]) -> str:
        widget_type = element.get("widgetType") or ""
        if not widget_type:
            return ""
        settings = element.get("settings")
        settings = settings if isinstance(settings, dict) else {}

        local = self.UNIVERSAL_WIDGET_MAP.get(widget_type, "text")
        module_content = self._universal_module_content(widget_type, settings)
        self._overlay_responsive(element, module_content)

        attrs: Dict[str, Any] = {}
        if module_content:
            attrs["content"] = module_content
        attrs["builderVersion"] = TARGET_CMS_VERSION
        attrs_json = self._serialize_attrs(attrs)

        # Content that cannot survive the escaped attribute JSON verbatim
        # (markup, newlines, list items with no native field) ships as the
        # block's static inner render instead of being dropped.
        inner = self._leftover_content_html(settings, attrs_json)
        children = "".join(
            self._render_universal(c) for c in self._universal_children(element)
        )
        body = inner + children
        if body:
            return (
                f"<!-- wp:{BLOCK_NAMESPACE}{local} {attrs_json} -->\n"
                f"{body}"
                f"<!-- /wp:{BLOCK_NAMESPACE}{local} -->\n"
            )
        return f"<!-- wp:{BLOCK_NAMESPACE}{local} {attrs_json} /-->\n"

    def _universal_module_content(self, widget_type: str, settings: Dict[str, Any]) -> Dict[str, Any]:
        """Native DIVI 5 content fields from the canonical settings vocabulary."""
        mc: Dict[str, Any] = {}

        def put(field: str, value: Any) -> None:
            if value not in (None, ""):
                mc[field] = self._responsive(value)

        link = settings.get("link")
        link = link if isinstance(link, dict) else {}

        if widget_type == "heading":
            put("text", settings.get("title", ""))
            put("level", settings.get("header_size", "h2"))
        elif widget_type in ("text-editor", "text", "paragraph"):
            put("innerContent", settings.get("editor", settings.get("text", "")))
        elif widget_type == "button":
            put("text", settings.get("text", ""))
            put("url", link.get("url", ""))
            if link.get("is_external"):
                mc["urlNewWindow"] = True
        elif widget_type == "image":
            image = settings.get("image")
            image = image if isinstance(image, dict) else {}
            put("src", image.get("url", ""))
            put("alt", image.get("alt", ""))
        elif widget_type == "video":
            put("src", settings.get("youtube_url", ""))
        elif widget_type == "audio":
            put("audio", link.get("url", ""))
            put("title", settings.get("title", ""))
        elif widget_type == "icon":
            icon = settings.get("selected_icon")
            if isinstance(icon, dict):
                put("icon", icon.get("value", ""))
        elif widget_type == "icon-box":
            put("title", settings.get("title_text", ""))
            put("content", settings.get("description_text", ""))
            put("url", link.get("url", ""))
        elif widget_type in ("testimonial", "blockquote"):
            put("content", settings.get("testimonial_content", settings.get("blockquote_content", "")))
            put("author", settings.get("testimonial_name", settings.get("author", "")))
            put("jobTitle", settings.get("testimonial_job", ""))
        elif widget_type == "call-to-action":
            put("title", settings.get("title", ""))
            put("content", settings.get("description", ""))
            put("buttonText", settings.get("button_text", ""))
            put("url", link.get("url", ""))
        elif widget_type == "price-table":
            put("title", settings.get("heading", ""))
            price = settings.get("price", "")
            if price:
                put("sum", f"{settings.get('currency_symbol', '')}{price}")
        elif widget_type == "alert":
            put("title", settings.get("alert_title", ""))
            put("content", settings.get("alert_description", ""))
        elif widget_type == "counter":
            put("title", settings.get("title", ""))
            put("number", settings.get("ending_number", ""))
        elif widget_type == "progress":
            put("title", settings.get("title", ""))
            put("percent", settings.get("percent", ""))
        elif widget_type == "countdown":
            put("title", settings.get("title", ""))
            put("dateTime", settings.get("due_date", ""))
        elif widget_type == "google_maps":
            put("address", settings.get("address", ""))
        elif widget_type in ("form", "slides", "slider", "nav", "nav-menu"):
            put("title", settings.get("title", ""))
        return mc

    def _leftover_content_html(self, settings: Dict[str, Any], attrs_json: str) -> str:
        """Render content-bearing settings absent from the serialized attrs
        (verbatim) as inner HTML — the lossless fallback for every widget."""
        parts: List[str] = []
        seen = set()
        for value in _content_setting_strings(settings):
            if value in attrs_json or value in seen:
                continue
            seen.add(value)
            parts.append(value if "<" in value else f"<p>{value}</p>")
        return "\n".join(parts) + ("\n" if parts else "")

    def _responsive(self, value: Any) -> Dict[str, Dict[str, Any]]:
        """Wrap a scalar in DIVI 5's desktop responsive variant."""
        return {"desktop": {"value": value}}

    @staticmethod
    def _serialize_attrs(attrs: Dict[str, Any]) -> str:
        """Serialize block attrs the way WP's serialize_block_attributes does.

        HTML inside the JSON must use unicode escapes so it cannot break the
        surrounding block-comment delimiters (``--``, ``<``, ``>``).
        """
        encoded = json.dumps(attrs, separators=(",", ":"))
        encoded = encoded.replace("--", "\\u002d\\u002d")
        encoded = encoded.replace("<", "\\u003c")
        encoded = encoded.replace(">", "\\u003e")
        encoded = encoded.replace("&", "\\u0026")
        encoded = encoded.replace('\\"', "\\u0022")
        return encoded
