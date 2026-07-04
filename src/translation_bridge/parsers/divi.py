"""
Translation Bridge v4 - DIVI 4 Shortcode Source Parser.

Parses classic DIVI ``[et_pb_*]`` shortcodes into the universal element
shape (Section > Row > Column > Module hierarchy). DIVI 5 block markup
routes to ``Divi5Parser`` instead.
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
    "et_pb_section": "section",
    "et_pb_row": "container",
    "et_pb_row_inner": "container",
    "et_pb_column": "column",
    "et_pb_column_inner": "column",
}

_WIDGET_TYPES = {
    "et_pb_text": "text-editor",
    "et_pb_heading": "heading",
    "et_pb_image": "image",
    "et_pb_button": "button",
    "et_pb_blurb": "icon-box",
    "et_pb_toggle": "accordion",
    "et_pb_accordion": "accordion",
    "et_pb_accordion_item": "accordion",
    "et_pb_tabs": "tabs",
    "et_pb_tab": "tabs",
    "et_pb_testimonial": "testimonial",
    "et_pb_cta": "call-to-action",
    "et_pb_code": "html",
    "et_pb_video": "video",
    "et_pb_audio": "audio",
    "et_pb_gallery": "image-gallery",
    "et_pb_divider": "divider",
    "et_pb_number_counter": "counter",
    "et_pb_circle_counter": "counter",
    "et_pb_bar_counters": "progress",
    "et_pb_countdown_timer": "countdown",
    "et_pb_contact_form": "form",
    "et_pb_social_media_follow": "social-icons",
    "et_pb_map": "google_maps",
    "et_pb_pricing_tables": "price-table",
    "et_pb_slider": "slides",
    "et_pb_slide": "slides",
}

_STRIP_TAGS_RE = re.compile(r"<[^>]+>")
_HEADING_RE = re.compile(r"<h([1-6])[^>]*>(.*?)</h[1-6]>", re.S)


@ParserRegistry.register(
    name="divi_parser",
    framework="divi",
    description="Classic DIVI [et_pb_*] shortcode parser",
    version="4.11.0",
    file_extensions=[".txt", ".html"],
)
class DiviParser:
    """Parse DIVI 4 shortcodes into a UniversalDocument."""

    def parse_file(self, file_path: str) -> UniversalDocument:
        with open(file_path, "r", encoding="utf-8") as handle:
            return self.parse(handle.read())

    def parse(self, content: Union[str, List[str]]) -> UniversalDocument:
        if isinstance(content, list):
            content = "\n".join(content)
        if not isinstance(content, str) or not content.strip():
            return UniversalDocument()
        # DIVI 5 block markup is a different format entirely.
        if re.search(r"<!--\s*/?wp:divi/", content):
            return UniversalDocument()

        nodes = parse_shortcodes(content, ("et_pb_",))
        roots = [
            el for node in nodes if (el := self._parse_node(node, False))
        ]
        return UniversalDocument(elements=roots, meta={"source_framework": "divi"})

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
            # DIVI text modules may embed headings — surface them as title.
            heading = _HEADING_RE.search(content)
            if heading and not text.replace(_STRIP_TAGS_RE.sub("", heading.group(2)).strip(), "").strip():
                pass  # pure-heading text module stays a text widget with the full content
            out["editor"] = text
        elif widget_type == "heading":
            out["title"] = attrs.get("title", text)
            out["header_size"] = attrs.get("heading_level", "h2")
        elif widget_type == "button":
            out["text"] = attrs.get("button_text", text)
            url = attrs.get("button_url", attrs.get("url", ""))
            if url:
                out["link"] = {"url": url}
                if attrs.get("url_new_window") == "on":
                    out["link"]["is_external"] = "on"
        elif widget_type == "image":
            out["image"] = {"url": attrs.get("src", "")}
            if attrs.get("alt"):
                out["image"]["alt"] = attrs["alt"]
        elif widget_type == "icon-box":
            out["title_text"] = attrs.get("title", "")
            out["description_text"] = text
        elif widget_type == "accordion":
            if attrs.get("title") or text:
                out["tabs"] = [{"tab_title": attrs.get("title", ""), "tab_content": text}]
        elif widget_type == "testimonial":
            out["testimonial_content"] = text
            out["testimonial_name"] = attrs.get("author", "")
            job = ", ".join(p for p in (attrs.get("job_title"), attrs.get("company_name")) if p)
            out["testimonial_job"] = job
        elif widget_type == "call-to-action":
            out["title"] = attrs.get("title", "")
            out["description"] = text
            if attrs.get("button_text"):
                out["button_text"] = attrs["button_text"]
            if attrs.get("button_url"):
                out["link"] = {"url": attrs["button_url"]}
        elif widget_type == "html":
            out["html"] = content
        elif widget_type == "counter":
            out["title"] = attrs.get("title", "")
            out["ending_number"] = attrs.get("number", "")
        elif widget_type == "video":
            out["youtube_url"] = attrs.get("src", "")
        elif widget_type in ("countdown", "form", "google_maps", "slides"):
            if attrs.get("title"):
                out["title"] = attrs["title"]
                out["form_name"] = attrs["title"]
        elif text:
            out["text"] = text

        return out

    # CLI surface -------------------------------------------------------

    def extract_content(self, doc: UniversalDocument) -> Dict[str, List[str]]:
        return extract_document_content(doc)

    def analyze(self, doc: UniversalDocument) -> Dict[str, Any]:
        return analyze_document(doc)

    def get_framework(self) -> str:
        return "divi"
