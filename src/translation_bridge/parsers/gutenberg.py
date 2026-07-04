"""
Translation Bridge v4 - Gutenberg Block-Markup Source Parser.

Parses WordPress core block markup (``<!-- wp:heading -->`` ...) into the
universal element shape, unlocking Gutenberg content as a SOURCE on the
lossless ``transform`` path. Canonical core blocks map onto the universal
widget vocabulary; unknown blocks preserve their inner HTML as ``html``
widgets so nothing is silently dropped.
"""

from __future__ import annotations

import re
from typing import Any, Dict, List, Optional, Union

from translation_bridge.parsers.blocks import parse_block_markup, strip_tags
from translation_bridge.parsers.universal import (
    UniversalDocument,
    UniversalElement,
    analyze_document,
    extract_document_content,
)
from translation_bridge.transforms.registry import ParserRegistry


_CONTAINER_TYPES = {
    "core/group": "container",
    "core/columns": "container",
    "core/column": "column",
    "core/cover": "section",
    "core/buttons": "container",
}

_HEADING_RE = re.compile(r"<h([1-6])[^>]*>(.*?)</h[1-6]>", re.S)
_IMG_RE = re.compile(r'<img[^>]*src="([^"]*)"[^>]*?(?:alt="([^"]*)")?[^>]*/?>', re.S)
_LINK_RE = re.compile(r'<a[^>]*href="([^"]*)"[^>]*>(.*?)</a>', re.S)
_CITE_RE = re.compile(r"<cite[^>]*>(.*?)</cite>", re.S)
_LIST_ITEM_RE = re.compile(r"<li[^>]*>(.*?)</li>", re.S)


@ParserRegistry.register(
    name="gutenberg_parser",
    framework="gutenberg",
    description="WordPress core block-markup parser",
    version="4.10.0",
    file_extensions=[".html", ".txt"],
)
class GutenbergParser:
    """Parse Gutenberg block markup into a UniversalDocument."""

    def parse_file(self, file_path: str) -> UniversalDocument:
        with open(file_path, "r", encoding="utf-8") as handle:
            return self.parse(handle.read())

    def parse(self, content: Union[str, List[str]]) -> UniversalDocument:
        if isinstance(content, list):
            content = "\n".join(content)
        if not isinstance(content, str) or not content.strip():
            return UniversalDocument()

        blocks = parse_block_markup(content)
        roots = [
            el
            for block in blocks
            if (el := self._parse_block(block, False))
        ]
        return UniversalDocument(elements=roots, meta={"source_framework": "gutenberg"})

    def _parse_block(self, block: Dict[str, Any], is_inner: bool) -> Optional[UniversalElement]:
        name = block.get("blockName") or ""
        if not name:
            return None
        # Core blocks serialize without the namespace (`wp:heading` means
        # `core/heading`) — mirror WP's parse_blocks() normalization.
        if "/" not in name:
            name = f"core/{name}"
        attrs = block.get("attrs") if isinstance(block.get("attrs"), dict) else {}
        inner_html = block.get("innerHTML", "")

        if name in _CONTAINER_TYPES:
            element = UniversalElement(
                id="",
                el_type=_CONTAINER_TYPES[name],
                settings={},
                is_inner=is_inner,
            )
            for inner in block.get("innerBlocks", []):
                child = self._parse_block(inner, True)
                if child:
                    element.elements.append(child)
            return element

        widget = self._widget_for(name, attrs, inner_html, block)
        if widget is None:
            return None
        widget.is_inner = is_inner
        for inner in block.get("innerBlocks", []):
            child = self._parse_block(inner, True)
            if child:
                widget.elements.append(child)
        return widget

    def _widget_for(
        self, name: str, attrs: Dict[str, Any], inner_html: str, block: Dict[str, Any]
    ) -> Optional[UniversalElement]:
        settings: Dict[str, Any] = {}
        widget_type = "html"

        if name == "core/heading":
            widget_type = "heading"
            match = _HEADING_RE.search(inner_html)
            settings["title"] = strip_tags(match.group(2)) if match else strip_tags(inner_html)
            level = attrs.get("level") or (match.group(1) if match else 2)
            settings["header_size"] = f"h{level}"
        elif name == "core/paragraph":
            widget_type = "text-editor"
            settings["editor"] = strip_tags(inner_html)
        elif name == "core/button":
            widget_type = "button"
            link = _LINK_RE.search(inner_html)
            settings["text"] = strip_tags(link.group(2)) if link else strip_tags(inner_html)
            url = attrs.get("url") or (link.group(1) if link else "")
            if url:
                settings["link"] = {"url": url}
        elif name == "core/image":
            widget_type = "image"
            match = _IMG_RE.search(inner_html)
            settings["image"] = {"url": attrs.get("url") or (match.group(1) if match else "")}
            alt = match.group(2) if match and match.group(2) else attrs.get("alt")
            if alt:
                settings["image"]["alt"] = alt
        elif name == "core/quote":
            widget_type = "testimonial"
            cite = _CITE_RE.search(inner_html)
            body = _CITE_RE.sub("", inner_html)
            settings["testimonial_content"] = strip_tags(body)
            if cite:
                settings["testimonial_name"] = strip_tags(cite.group(1))
        elif name == "core/list":
            widget_type = "icon-list"
            settings["icon_list"] = [
                {"text": strip_tags(item)} for item in _LIST_ITEM_RE.findall(inner_html)
            ]
        elif name == "core/separator":
            widget_type = "divider"
        elif name == "core/spacer":
            widget_type = "spacer"
        elif name in ("core/html", "core/code", "core/shortcode"):
            widget_type = "html"
            settings["html"] = inner_html
        elif name == "core/gallery":
            widget_type = "image-gallery"
            settings["wp_gallery"] = [
                {"url": match.group(1), "alt": match.group(2) or ""}
                for match in _IMG_RE.finditer(inner_html)
            ]
        elif name == "core/video":
            widget_type = "video"
            src = re.search(r'src="([^"]*)"', inner_html)
            settings["youtube_url"] = attrs.get("src") or (src.group(1) if src else "")
        else:
            # Unknown block — preserve verbatim, never drop.
            if not inner_html and not block.get("innerBlocks"):
                return None
            settings["html"] = inner_html

        return UniversalElement(
            id="",
            el_type="widget",
            widget_type=widget_type,
            settings=settings,
        )

    # ------------------------------------------------------------------
    # CLI surface
    # ------------------------------------------------------------------

    def extract_content(self, doc: UniversalDocument) -> Dict[str, List[str]]:
        return extract_document_content(doc)

    def analyze(self, doc: UniversalDocument) -> Dict[str, Any]:
        return analyze_document(doc)

    def get_framework(self) -> str:
        return "gutenberg"
