"""
Translation Bridge v4 - Kadence Blocks Source Parser.

Parses Kadence Blocks markup (``kadence/*`` blocks with ``core/*``
fallthrough) into the universal element shape. Extends the Gutenberg parser:
core blocks are handled identically; ``kadence/*`` blocks add row layouts,
advanced headings/buttons, info boxes, spacers, and icons.
"""

from __future__ import annotations

import re
from typing import Any, Dict, List, Optional

from translation_bridge.parsers.blocks import strip_tags
from translation_bridge.parsers.gutenberg import GutenbergParser
from translation_bridge.parsers.universal import UniversalDocument, UniversalElement
from translation_bridge.transforms.registry import ParserRegistry

_BTN_TEXT_RE = re.compile(r'<span[^>]*class="[^"]*kt-btn-text[^"]*"[^>]*>(.*?)</span>', re.S)
_INFOBOX_TEXT_RE = re.compile(
    r'<div[^>]*class="[^"]*kt-blocks-info-box-text[^"]*"[^>]*>(.*?)</div>', re.S
)
_HEADING_RE = re.compile(r"<h([1-6])[^>]*>(.*?)</h[1-6]>", re.S)
_HREF_RE = re.compile(r'href="([^"]*)"')


@ParserRegistry.register(
    name="kadence_parser",
    framework="kadence",
    description="Kadence Blocks markup parser (kadence/* + core/* fallthrough)",
    version="4.11.0",
    file_extensions=[".html", ".txt"],
)
class KadenceParser(GutenbergParser):
    """Parse Kadence Blocks markup into a UniversalDocument."""

    def parse(self, content) -> UniversalDocument:
        doc = super().parse(content)
        doc.meta["source_framework"] = "kadence"
        return doc

    def _parse_block(self, block: Dict[str, Any], is_inner: bool) -> Optional[UniversalElement]:
        name = block.get("blockName") or ""
        if not name.startswith("kadence/"):
            return super()._parse_block(block, is_inner)

        local = name[len("kadence/") :]
        attrs = block.get("attrs") if isinstance(block.get("attrs"), dict) else {}
        inner_html = block.get("innerHTML", "")

        if local in ("rowlayout", "column", "section"):
            element = UniversalElement(
                id=str(attrs.get("uniqueID", "")),
                el_type="section" if local == "rowlayout" and not is_inner else (
                    "column" if local == "column" else "container"
                ),
                settings={},
                is_inner=is_inner,
            )
            for inner in block.get("innerBlocks", []):
                child = self._parse_block(inner, True)
                if child:
                    element.elements.append(child)
            return element

        settings: Dict[str, Any] = {}
        widget_type = "html"

        if local == "advancedheading":
            widget_type = "heading"
            match = _HEADING_RE.search(inner_html)
            settings["title"] = strip_tags(match.group(2)) if match else strip_tags(inner_html)
            level = attrs.get("level") or (match.group(1) if match else 2)
            settings["header_size"] = f"h{level}"
        elif local in ("advancedbtn", "singlebtn"):
            widget_type = "button"
            match = _BTN_TEXT_RE.search(inner_html)
            settings["text"] = strip_tags(match.group(1)) if match else strip_tags(inner_html)
            href = attrs.get("link") or (
                _HREF_RE.search(inner_html).group(1) if _HREF_RE.search(inner_html) else ""
            )
            if href:
                settings["link"] = {"url": href}
        elif local == "infobox":
            widget_type = "icon-box"
            match = _INFOBOX_TEXT_RE.search(inner_html)
            settings["title_text"] = str(attrs.get("title", ""))
            settings["description_text"] = (
                strip_tags(match.group(1)) if match else strip_tags(inner_html)
            )
        elif local == "spacer":
            widget_type = "spacer"
        elif local == "icon":
            widget_type = "icon"
        elif local in ("tabs", "accordion"):
            widget_type = local
        else:
            settings["html"] = inner_html
            if not inner_html and not block.get("innerBlocks"):
                return None

        element = UniversalElement(
            id=str(attrs.get("uniqueID", "")),
            el_type="widget",
            widget_type=widget_type,
            settings=settings,
            is_inner=is_inner,
        )
        for inner in block.get("innerBlocks", []):
            child = self._parse_block(inner, True)
            if child:
                element.elements.append(child)
        return element

    def get_framework(self) -> str:
        return "kadence"
