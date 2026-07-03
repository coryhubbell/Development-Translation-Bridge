"""
Translation Bridge v4 - Classic Oxygen (4.x) JSON Source Parser.

Parses classic Oxygen Builder content into the universal element shape so
Oxygen sites can ride the lossless ``transform`` path. Mirrors the hardened
PHP parser (v4.6.0): every real storage shape is accepted —

- the nested ``ct_builder_json`` root tree
  (``{"id":0,"name":"root","children":[...]}``),
- the ``{"ct_builder_json": {...}}`` wrapper,
- the flat ``ct_parent``-linked element list,
- ``ct_builder_shortcodes`` strings (``[ct_section ct_options='{...}']``).

Design props in ``options.original`` become element styles (with classic
Oxygen's unitless numerics normalized to px), and responsive overrides in
``options.media.<breakpoint>.original`` canonicalize into the shared
responsive model (tablet → tablet, phone-portrait → phone).
"""

from __future__ import annotations

import html
import json
import re
from typing import Any, Dict, List, Optional, Union

from translation_bridge.parsers.universal import (
    UniversalDocument,
    UniversalElement,
    analyze_document,
    extract_document_content,
)
from translation_bridge.transforms.registry import ParserRegistry


# Classic Oxygen breakpoint key -> canonical breakpoint.
_MEDIA_BREAKPOINTS = {"tablet": "tablet", "phone-portrait": "phone"}

# Length props classic Oxygen stores unitless (px implied).
_UNITLESS_PX_PROPS = frozenset(
    [
        "font-size", "border-radius", "border-width", "width", "height",
        "max-width", "min-width", "max-height", "min-height", "gap",
        "padding-top", "padding-right", "padding-bottom", "padding-left",
        "margin-top", "margin-right", "margin-bottom", "margin-left",
        "top", "right", "bottom", "left", "letter-spacing",
    ]
)

# Real classic element name -> (elType, widgetType). Containers map to
# section/column/container elTypes; leaves map to widget types. Legacy
# fabricated aliases from earlier releases parse too.
_CONTAINER_TYPES = {
    "ct_section": "section",
    "ct_div_block": "column",
    "ct_new_columns": "container",
    "ct_columns": "container",
    "ct_column": "column",
    "ct_inner_content": "container",
    "oxy_superbox": "container",
}

_WIDGET_TYPES = {
    "ct_headline": "heading",
    "ct_text_block": "text-editor",
    "oxy_rich_text": "text-editor",
    "ct_span": "text-editor",
    "ct_link": "button",
    "ct_link_text": "button",
    "ct_link_button": "button",
    "ct_image": "image",
    "ct_video": "video",
    "ct_code_block": "html",
    "oxy_shortcode": "html",
    "ct_fancy_icon": "icon",
    "ct_svg_icon": "icon",
    "ct_icon": "icon",
    "oxy_icon_box": "icon-box",
    "oxy_testimonial_box": "testimonial",
    "ct_testimonial": "testimonial",
    "oxy_pricing_box": "price-table",
    "ct_pricing_box": "price-table",
    "oxy_progress_bar": "progress",
    "ct_progress_bar": "progress",
    "oxy_map": "google_maps",
    "ct_google_map": "google_maps",
    "oxy_gallery": "gallery",
    "oxy_nav_menu": "nav",
    "ct_nav_menu": "nav",
    "ct_menu": "nav",
    "oxy_tabs": "tabs",
    "ct_tabs": "tabs",
    "oxy_toggle": "accordion",
    "ct_accordion": "accordion",
    "ct_toggle": "accordion",
    "ct_slider": "slides",
    "ct_separator": "divider",
    "oxy_posts_grid": "posts",
    "oxy_login_form": "form",
    "oxy_search_form": "form",
}


@ParserRegistry.register(
    name="oxygen_parser",
    framework="oxygen",
    description="Classic Oxygen (4.x) parser — all storage shapes",
    version="4.7.0",
    file_extensions=[".json"],
)
class OxygenParser:
    """Parse classic Oxygen content into a UniversalDocument."""

    def parse_file(self, file_path: str) -> UniversalDocument:
        with open(file_path, "r", encoding="utf-8") as handle:
            raw = handle.read()
        return self.parse(raw)

    def parse(self, data: Union[str, Dict[str, Any], List[Any]]) -> UniversalDocument:
        # Shortcode storage.
        if isinstance(data, str) and data.lstrip().startswith(("[ct_", "[oxy_")):
            elements = self._parse_shortcodes(data)
            return self._document(self._build_from_flat(elements))

        if isinstance(data, str):
            data = json.loads(data)

        if isinstance(data, dict):
            # Wrapper shape.
            inner = data.get("ct_builder_json")
            if inner is not None:
                if isinstance(inner, str):
                    inner = json.loads(inner)
                if isinstance(inner, dict):
                    data = inner.get("ct_builder", inner)

        if isinstance(data, dict):
            # Nested root tree.
            if data.get("name") == "root":
                roots = [
                    el
                    for child in data.get("children") or []
                    if isinstance(child, dict) and (el := self._parse_tree(child, False))
                ]
                return self._document(roots)
            if "name" in data:
                node = self._parse_tree(data, False)
                return self._document([node] if node else [])
            return UniversalDocument()

        if isinstance(data, list):
            elements = [el for el in data if isinstance(el, dict)]
            if any(isinstance(el.get("children"), list) and el["children"] for el in elements):
                roots = [
                    node
                    for el in elements
                    if (node := self._parse_tree(el, False))
                ]
                return self._document(roots)
            return self._document(self._build_from_flat(elements))

        return UniversalDocument()

    def _document(self, roots: List[UniversalElement]) -> UniversalDocument:
        return UniversalDocument(elements=roots, meta={"source_framework": "oxygen"})

    # ------------------------------------------------------------------
    # Input-shape handling
    # ------------------------------------------------------------------

    def _parse_shortcodes(self, content: str) -> List[Dict[str, Any]]:
        elements = []
        for match in re.finditer(r"\[(ct_[a-z0-9_]+|oxy_[a-z0-9_]+)\b([^\]]*)\]", content, re.I):
            name, attrs = match.group(1), match.group(2)
            options: Dict[str, Any] = {}
            opt_match = re.search(r"ct_options=([\"'])(.*?)\1", attrs, re.S)
            if opt_match:
                try:
                    decoded = json.loads(html.unescape(opt_match.group(2)))
                    if isinstance(decoded, dict):
                        options = decoded
                except json.JSONDecodeError:
                    pass
            elements.append(
                {"id": options.get("ct_id", len(elements) + 1), "name": name, "options": options}
            )
        return elements

    def _build_from_flat(self, elements: List[Dict[str, Any]]) -> List[UniversalElement]:
        def children_of(parent_id: Any) -> List[Dict[str, Any]]:
            out = []
            for el in elements:
                parent = (el.get("options") or {}).get("ct_parent", 0)
                if parent and str(parent) == str(parent_id):
                    out.append(el)
            return out

        def build(element: Dict[str, Any], is_inner: bool) -> Optional[UniversalElement]:
            node = self._convert_element(element, is_inner)
            if node is None:
                return None
            ct_id = (element.get("options") or {}).get("ct_id", element.get("id"))
            for child in children_of(ct_id):
                child_node = build(child, True)
                if child_node:
                    node.elements.append(child_node)
            return node

        roots = []
        for element in elements:
            parent = (element.get("options") or {}).get("ct_parent", 0)
            if parent in (0, "0", "", None):
                root = build(element, False)
                if root:
                    roots.append(root)
        return roots

    def _parse_tree(self, element: Dict[str, Any], is_inner: bool) -> Optional[UniversalElement]:
        node = self._convert_element(element, is_inner)
        if node is None:
            return None
        for child in element.get("children") or []:
            if isinstance(child, dict):
                child_node = self._parse_tree(child, True)
                if child_node:
                    node.elements.append(child_node)
        return node

    # ------------------------------------------------------------------
    # Element normalization
    # ------------------------------------------------------------------

    def _convert_element(self, element: Dict[str, Any], is_inner: bool) -> Optional[UniversalElement]:
        name = str(element.get("name", ""))
        if not name or name == "root":
            return None

        options = element.get("options") if isinstance(element.get("options"), dict) else {}
        element_id = str(options.get("ct_id", element.get("id", "")))

        responsive = self._canonicalize_media(options)

        if name in _CONTAINER_TYPES:
            return UniversalElement(
                id=element_id,
                el_type=_CONTAINER_TYPES[name],
                settings=self._styles_from_original(options),
                is_inner=is_inner,
                responsive=responsive,
            )

        widget_type = _WIDGET_TYPES.get(name, "text-editor")
        settings = self._widget_settings(name, widget_type, options)
        settings.update(self._styles_from_original(options))

        return UniversalElement(
            id=element_id,
            el_type="widget",
            widget_type=widget_type,
            settings=settings,
            is_inner=is_inner,
            responsive=responsive,
        )

    def _widget_settings(self, name: str, widget_type: str, options: Dict[str, Any]) -> Dict[str, Any]:
        out: Dict[str, Any] = {}
        content = options.get("ct_content", "")

        if widget_type == "heading":
            out["title"] = content or options.get("headline_text", "")
            tag = str(options.get("tag", "h2"))
            out["header_size"] = tag if re.fullmatch(r"h\d", tag) else "h2"
        elif widget_type == "text-editor":
            out["editor"] = content or options.get("text", "")
        elif widget_type == "button":
            out["text"] = content or options.get("text", "")
            if options.get("url"):
                out["link"] = {"url": options["url"]}
                if options.get("target") == "_blank":
                    out["link"]["is_external"] = "on"
        elif widget_type == "image":
            out["image"] = {"url": options.get("src", "")}
            if options.get("image_id") or options.get("attachment_id"):
                out["image"]["id"] = options.get("image_id", options.get("attachment_id"))
            if options.get("alt"):
                out["image"]["alt"] = options["alt"]
        elif widget_type == "html":
            out["html"] = content or options.get("code", options.get("full_shortcode", ""))
        elif widget_type == "icon":
            if options.get("icon"):
                out["selected_icon"] = {"value": options["icon"]}
        elif widget_type == "testimonial":
            out["testimonial_content"] = content or options.get(
                "testimonial_text", options.get("quote", "")
            )
            out["testimonial_name"] = options.get("author", "")
            out["testimonial_job"] = options.get("title", "")
        elif widget_type == "icon-box":
            out["title_text"] = content or options.get("heading", "")
            out["description_text"] = options.get("text", "")
        elif content:
            out["text"] = content

        return out

    # ------------------------------------------------------------------
    # Styles + responsive
    # ------------------------------------------------------------------

    def _styles_from_original(self, options: Dict[str, Any]) -> Dict[str, Any]:
        original = options.get("original")
        if not isinstance(original, dict):
            return {}
        return self._normalize_units(original)

    def _normalize_units(self, props: Dict[str, Any]) -> Dict[str, str]:
        out = {}
        for prop, value in props.items():
            if not isinstance(value, (str, int, float)):
                continue
            value = str(value)
            if not value:
                continue
            if prop in _UNITLESS_PX_PROPS and re.fullmatch(r"-?\d+(?:\.\d+)?", value):
                value += "px"
            out[str(prop)] = value
        return out

    def _canonicalize_media(self, options: Dict[str, Any]) -> Optional[Dict[str, Any]]:
        media = options.get("media")
        if not isinstance(media, dict):
            return None

        canonical: Dict[str, Any] = {}
        for oxygen_key, breakpoint in _MEDIA_BREAKPOINTS.items():
            original = (media.get(oxygen_key) or {}).get("original")
            if isinstance(original, dict) and original:
                canonical[breakpoint] = {"default": self._normalize_units(original)}

        if not canonical:
            return None

        base = self._styles_from_original(options)
        if base:
            canonical["desktop"] = {"default": base}

        return {"styles": canonical}

    # ------------------------------------------------------------------
    # CLI surface
    # ------------------------------------------------------------------

    def extract_content(self, doc: UniversalDocument) -> Dict[str, List[str]]:
        return extract_document_content(doc)

    def analyze(self, doc: UniversalDocument) -> Dict[str, Any]:
        return analyze_document(doc)

    def get_framework(self) -> str:
        return "oxygen"
