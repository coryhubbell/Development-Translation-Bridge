"""
Translation Bridge v4 - Avada Fusion Builder ([fusion_*]) Shortcode Source Parser.

Parses Avada Fusion Builder shortcodes into the universal element shape
(Container > Row > Column > Element hierarchy).
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
    "fusion_builder_container": "section",
    "fusion_builder_row": "container",
    "fusion_builder_row_inner": "container",
    "fusion_builder_column": "column",
    "fusion_builder_column_inner": "column",
}

_WIDGET_TYPES = {
    "fusion_text": "text-editor",
    "fusion_title": "heading",
    "fusion_button": "button",
    "fusion_imageframe": "image",
    "fusion_alert": "alert",
    "fusion_content_box": "icon-box",
    "fusion_flip_box": "icon-box",
    "fusion_tagline_box": "call-to-action",
    "fusion_testimonial": "testimonial",
    "fusion_tabs": "tabs",
    "fusion_tab": "tabs",
    "fusion_accordion": "accordion",
    "fusion_toggle": "accordion",
    "fusion_separator": "divider",
    "fusion_section_separator": "divider",
    "fusion_fontawesome": "icon",
    "fusion_code_block": "html",
    "fusion_video": "video",
    "fusion_youtube": "video",
    "fusion_vimeo": "video",
    "fusion_audio": "audio",
    "fusion_gallery": "image-gallery",
    "fusion_slider": "slides",
    "fusion_counter_box": "counter",
    "fusion_progressbar": "progress",
    "fusion_checklist": "icon-list",
    "fusion_map": "google_maps",
    "fusion_google_map": "google_maps",
    "fusion_pricing_table": "price-table",
    "fusion_social_links": "social-icons",
    "fusion_sharing_box": "social-icons",
}

_STRIP_TAGS_RE = re.compile(r"<[^>]+>")
_IMG_SRC_RE = re.compile(r'<img[^>]*src="([^"]*)"')


@ParserRegistry.register(
    name="avada_parser",
    framework="avada",
    description="Avada Fusion Builder [fusion_*] shortcode parser",
    version="4.11.0",
    file_extensions=[".txt", ".html"],
)
class AvadaParser:
    """Parse Avada Fusion Builder shortcodes into a UniversalDocument."""

    def parse_file(self, file_path: str) -> UniversalDocument:
        with open(file_path, "r", encoding="utf-8") as handle:
            return self.parse(handle.read())

    def parse(self, content: Union[str, List[str]]) -> UniversalDocument:
        if isinstance(content, list):
            content = "\n".join(content)
        if not isinstance(content, str) or not content.strip():
            return UniversalDocument()

        nodes = parse_shortcodes(content, ("fusion_",))
        roots = [el for node in nodes if (el := self._parse_node(node, False))]
        return UniversalDocument(elements=roots, meta={"source_framework": "avada"})

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
            out["title"] = attrs.get("title", text)
            size = attrs.get("size", "2")
            out["header_size"] = f"h{size}" if not size.startswith("h") else size
        elif widget_type == "button":
            out["text"] = text or attrs.get("title", "")
            url = attrs.get("link", attrs.get("url", ""))
            if url:
                out["link"] = {"url": url}
                if attrs.get("target") == "_blank":
                    out["link"]["is_external"] = "on"
        elif widget_type == "image":
            src = attrs.get("src", attrs.get("image", ""))
            if not src:
                match = _IMG_SRC_RE.search(content)
                src = match.group(1) if match else content.strip()
            out["image"] = {"url": src if src.startswith(("http", "/")) else ""}
            if attrs.get("alt"):
                out["image"]["alt"] = attrs["alt"]
        elif widget_type == "alert":
            out["alert_title"] = attrs.get("title", "")
            out["alert_description"] = text
            if attrs.get("type"):
                out["alert_type"] = attrs["type"]
        elif widget_type == "icon-box":
            out["title_text"] = attrs.get("title", attrs.get("title_front", ""))
            out["description_text"] = text
        elif widget_type == "call-to-action":
            out["title"] = attrs.get("title", "")
            out["description"] = text
            if attrs.get("button"):
                out["button_text"] = attrs["button"]
            if attrs.get("link"):
                out["link"] = {"url": attrs["link"]}
        elif widget_type == "testimonial":
            out["testimonial_content"] = text
            out["testimonial_name"] = attrs.get("name", "")
            out["testimonial_job"] = attrs.get("company", "")
        elif widget_type == "accordion":
            if attrs.get("title") or text:
                out["tabs"] = [{"tab_title": attrs.get("title", ""), "tab_content": text}]
        elif widget_type == "html":
            out["html"] = content
        elif widget_type == "video":
            out["youtube_url"] = attrs.get("id", attrs.get("link", ""))
        elif widget_type == "counter":
            out["title"] = text or attrs.get("title", "")
            out["ending_number"] = attrs.get("value", "")
        elif widget_type == "icon-list":
            items = re.findall(r"\[fusion_li_item[^\]]*\](.*?)\[/fusion_li_item\]", content, re.S)
            out["icon_list"] = [
                {"text": _STRIP_TAGS_RE.sub("", item).strip()} for item in items
            ] or ([{"text": text}] if text else [])
        elif widget_type == "progress":
            out["title"] = text or attrs.get("title", "")
        elif text:
            out["text"] = text

        return out

    # CLI surface -------------------------------------------------------

    def extract_content(self, doc: UniversalDocument) -> Dict[str, List[str]]:
        return extract_document_content(doc)

    def analyze(self, doc: UniversalDocument) -> Dict[str, Any]:
        return analyze_document(doc)

    def get_framework(self) -> str:
        return "avada"
