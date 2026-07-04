"""
Translation Bridge v4 - WPBakery ([vc_*]) Shortcode Source Parser.

Parses WPBakery Page Builder shortcodes into the universal element shape
(Row > Column > Element hierarchy).
"""

from __future__ import annotations

import re
from typing import Any, Dict, List, Optional, Union

from translation_bridge.parsers.shortcodes import parse_shortcodes
from translation_bridge.parsers.universal import (
    UniversalDocument,
    UniversalElement,
    analyze_document,
    extract_document_content,
)
from translation_bridge.transforms.registry import ParserRegistry

_CONTAINER_TYPES = {
    "vc_section": "section",
    "vc_row": "container",
    "vc_row_inner": "container",
    "vc_column": "column",
    "vc_column_inner": "column",
    "vc_tta_tabs": "container",
    "vc_tta_accordion": "container",
}

_WIDGET_TYPES = {
    "vc_column_text": "text-editor",
    "vc_wp_text": "text-editor",
    "vc_custom_heading": "heading",
    "vc_btn": "button",
    "vc_button": "button",
    "vc_button2": "button",
    "vc_single_image": "image",
    "vc_message": "alert",
    "vc_cta": "call-to-action",
    "vc_toggle": "accordion",
    "vc_accordion_tab": "accordion",
    "vc_tta_section": "accordion",
    "vc_tab": "tabs",
    "vc_separator": "divider",
    "vc_text_separator": "divider",
    "vc_empty_space": "spacer",
    "vc_icon": "icon",
    "vc_raw_html": "html",
    "vc_raw_js": "html",
    "vc_video": "video",
    "vc_gmaps": "google_maps",
    "vc_progress_bar": "progress",
    "vc_gallery": "image-gallery",
    "vc_images_carousel": "slides",
    "vc_pie": "counter",
}

_STRIP_TAGS_RE = re.compile(r"<[^>]+>")


def _vc_link(raw: str) -> Dict[str, str]:
    """Parse WPBakery's `url:...|title:...|target:...` link format."""
    out: Dict[str, str] = {}
    from urllib.parse import unquote

    for part in raw.split("|"):
        if ":" in part:
            key, value = part.split(":", 1)
            out[key] = unquote(value)
    return out


@ParserRegistry.register(
    name="wpbakery_parser",
    framework="wpbakery",
    description="WPBakery [vc_*] shortcode parser",
    version="4.11.0",
    file_extensions=[".txt", ".html"],
)
class WPBakeryParser:
    """Parse WPBakery shortcodes into a UniversalDocument."""

    def parse_file(self, file_path: str) -> UniversalDocument:
        with open(file_path, "r", encoding="utf-8") as handle:
            return self.parse(handle.read())

    def parse(self, content: Union[str, List[str]]) -> UniversalDocument:
        if isinstance(content, list):
            content = "\n".join(content)
        if not isinstance(content, str) or not content.strip():
            return UniversalDocument()

        nodes = parse_shortcodes(content, ("vc_",))
        roots = [el for node in nodes if (el := self._parse_node(node, False))]
        return UniversalDocument(elements=roots, meta={"source_framework": "wpbakery"})

    def _parse_node(self, node: Dict[str, Any], is_inner: bool) -> Optional[UniversalElement]:
        tag = node["tag"]
        attrs = node["attrs"]

        if tag in _CONTAINER_TYPES:
            element = UniversalElement(
                id="", el_type=_CONTAINER_TYPES[tag], settings={}, is_inner=is_inner
            )
        else:
            widget_type = _WIDGET_TYPES.get(tag, "html" if node["content"] else "text-editor")
            element = UniversalElement(
                id="",
                el_type="widget",
                widget_type=widget_type,
                settings=self._widget_settings(tag, widget_type, attrs, node["content"]),
                is_inner=is_inner,
            )

        for child in node["children"]:
            child_el = self._parse_node(child, True)
            if child_el:
                element.elements.append(child_el)
        return element

    def _widget_settings(
        self, tag: str, widget_type: str, attrs: Dict[str, str], content: str
    ) -> Dict[str, Any]:
        out: Dict[str, Any] = {}
        text = _STRIP_TAGS_RE.sub("", content).strip()

        if widget_type == "text-editor":
            out["editor"] = text
        elif widget_type == "heading":
            out["title"] = attrs.get("text", text)
            match = re.search(r"tag:(h[1-6])", attrs.get("font_container", ""))
            out["header_size"] = match.group(1) if match else "h2"
        elif widget_type == "button":
            out["text"] = attrs.get("title", text)
            link = _vc_link(attrs.get("link", ""))
            if link.get("url"):
                out["link"] = {"url": link["url"]}
                if link.get("target", "").strip() == "_blank":
                    out["link"]["is_external"] = "on"
        elif widget_type == "image":
            out["image"] = {"url": attrs.get("source_external_url", attrs.get("image", ""))}
            if attrs.get("alt"):
                out["image"]["alt"] = attrs["alt"]
        elif widget_type == "alert":
            out["alert_title"] = attrs.get("title", "")
            out["alert_description"] = text
        elif widget_type == "call-to-action":
            out["title"] = attrs.get("h2", attrs.get("title", ""))
            out["description"] = text
            if attrs.get("btn_title"):
                out["button_text"] = attrs["btn_title"]
        elif widget_type == "accordion":
            if attrs.get("title") or text:
                out["tabs"] = [{"tab_title": attrs.get("title", ""), "tab_content": text}]
        elif widget_type == "html":
            # vc_raw_html content is base64-encoded by WPBakery.
            import base64

            raw = content.strip()
            try:
                decoded = base64.b64decode(raw, validate=True).decode("utf-8")
                out["html"] = decoded
            except Exception:  # noqa: BLE001
                out["html"] = content
        elif widget_type == "video":
            out["youtube_url"] = attrs.get("link", "")
        elif widget_type == "counter":
            out["title"] = attrs.get("label_value", attrs.get("title", ""))
            out["ending_number"] = attrs.get("value", "")
        elif widget_type == "progress":
            out["title"] = attrs.get("title", "")
        elif text:
            out["text"] = text

        return out

    # CLI surface -------------------------------------------------------

    def extract_content(self, doc: UniversalDocument) -> Dict[str, List[str]]:
        return extract_document_content(doc)

    def analyze(self, doc: UniversalDocument) -> Dict[str, Any]:
        return analyze_document(doc)

    def get_framework(self) -> str:
        return "wpbakery"
