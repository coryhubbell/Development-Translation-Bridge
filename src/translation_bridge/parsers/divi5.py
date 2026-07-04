"""
Translation Bridge v4 - DIVI 5 Block-Markup Source Parser.

Parses DIVI 5's ``wp:divi/*`` block markup into the universal element shape,
mirroring the verified format (v4.4.0): content lives in the TOP-LEVEL
``content`` attribute group with responsive ``{"desktop": {"value": ...}}``
wrappers (states nested inside breakpoints; ``value`` is the default state),
HTML unicode-escaped inside attrs (handled transparently by JSON decoding).

Tablet/phone breakpoints and hover states canonicalize into the shared
responsive model via ``divi5_wrapper_to_canonical``.
"""

from __future__ import annotations

from typing import Any, Dict, List, Optional, Union

from translation_bridge.parsers.blocks import parse_block_markup, strip_tags
from translation_bridge.parsers.universal import (
    UniversalDocument,
    UniversalElement,
    analyze_document,
    extract_document_content,
)
from translation_bridge.responsive import divi5_wrapper_to_canonical
from translation_bridge.transforms.registry import ParserRegistry


_CONTAINER_TYPES = {
    "section": "section",
    "row": "container",
    "column": "column",
    "placeholder": "container",
    "group": "container",
}

_WIDGET_TYPES = {
    "text": "text-editor",
    "heading": "heading",
    "button": "button",
    "image": "image",
    "video": "video",
    "audio": "audio",
    "gallery": "gallery",
    "divider": "divider",
    "blurb": "icon-box",
    "testimonial": "testimonial",
    "accordion": "accordion",
    "accordion-item": "accordion",
    "tabs": "tabs",
    "tab": "tabs",
    "slider": "slides",
    "slide": "slides",
    "code": "html",
    "pricing-table": "price-table",
    "counter": "counter",
    "progress": "progress",
    "social-media": "social-icons",
    "cta": "call-to-action",
    "contact-form": "form",
    "menu": "nav",
    "icon": "icon",
    "map": "google_maps",
    "countdown": "countdown",
}


def _desktop_value(wrapper: Any) -> str:
    """Resolve a DIVI 5 responsive wrapper to its desktop default value."""
    if isinstance(wrapper, dict):
        desktop = wrapper.get("desktop")
        if isinstance(desktop, dict) and "value" in desktop:
            return str(desktop["value"])
        if "value" in wrapper:
            return str(wrapper["value"])
        return ""
    return str(wrapper) if wrapper is not None else ""


@ParserRegistry.register(
    name="divi5_parser",
    framework="divi5",
    description="DIVI 5 wp:divi/* block-markup parser (verified format)",
    version="4.10.0",
    file_extensions=[".html", ".txt"],
)
class Divi5Parser:
    """Parse DIVI 5 block markup into a UniversalDocument."""

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
        return UniversalDocument(elements=roots, meta={"source_framework": "divi5"})

    def _parse_block(self, block: Dict[str, Any], is_inner: bool) -> Optional[UniversalElement]:
        name = block.get("blockName") or ""
        if not name.startswith("divi/"):
            # Recurse defensively through non-divi wrappers.
            for inner in block.get("innerBlocks", []):
                element = self._parse_block(inner, is_inner)
                if element:
                    return element
            return None

        local = name[len("divi/") :]
        attrs = block.get("attrs") if isinstance(block.get("attrs"), dict) else {}
        content_group = attrs.get("content") if isinstance(attrs.get("content"), dict) else {}
        # Legacy proxy shape fallback: module.content.
        if not content_group:
            module = attrs.get("module") if isinstance(attrs.get("module"), dict) else {}
            content_group = module.get("content") if isinstance(module.get("content"), dict) else {}

        responsive = self._canonicalize_fields(content_group)

        if local in _CONTAINER_TYPES:
            element = UniversalElement(
                id="",
                el_type=_CONTAINER_TYPES[local],
                settings={},
                is_inner=is_inner,
                responsive=responsive,
            )
        else:
            widget_type = _WIDGET_TYPES.get(local, "text-editor")
            element = UniversalElement(
                id="",
                el_type="widget",
                widget_type=widget_type,
                settings=self._widget_settings(local, widget_type, content_group, block),
                is_inner=is_inner,
                responsive=responsive,
            )

        for inner in block.get("innerBlocks", []):
            child = self._parse_block(inner, True)
            if child:
                element.elements.append(child)

        return element

    def _canonicalize_fields(self, content_group: Dict[str, Any]) -> Optional[Dict[str, Any]]:
        fields = {}
        for field, wrapper in content_group.items():
            canonical = divi5_wrapper_to_canonical(wrapper)
            if canonical is not None:
                fields[str(field)] = canonical
        return {"fields": fields} if fields else None

    def _widget_settings(
        self, local: str, widget_type: str, content_group: Dict[str, Any], block: Dict[str, Any]
    ) -> Dict[str, Any]:
        out: Dict[str, Any] = {}

        if widget_type == "heading":
            out["title"] = _desktop_value(content_group.get("text", content_group.get("title", "")))
            level = _desktop_value(content_group.get("level", content_group.get("tag", "h2")))
            out["header_size"] = level if level.startswith("h") else "h2"
        elif widget_type == "text-editor":
            text = _desktop_value(content_group.get("innerContent", ""))
            out["editor"] = text or strip_tags(block.get("innerHTML", ""))
        elif widget_type == "button":
            out["text"] = _desktop_value(content_group.get("text", ""))
            url = _desktop_value(content_group.get("url", ""))
            if url:
                out["link"] = {"url": url}
                if content_group.get("urlNewWindow"):
                    out["link"]["is_external"] = "on"
        elif widget_type == "image":
            out["image"] = {"url": _desktop_value(content_group.get("src", ""))}
            alt = _desktop_value(content_group.get("alt", ""))
            if alt:
                out["image"]["alt"] = alt
        elif widget_type == "html":
            out["html"] = _desktop_value(content_group.get("code", content_group.get("html", "")))
        else:
            text = _desktop_value(content_group.get("text", ""))
            if text:
                out["text"] = text

        return out

    # ------------------------------------------------------------------
    # CLI surface
    # ------------------------------------------------------------------

    def extract_content(self, doc: UniversalDocument) -> Dict[str, List[str]]:
        return extract_document_content(doc)

    def analyze(self, doc: UniversalDocument) -> Dict[str, Any]:
        return analyze_document(doc)

    def get_framework(self) -> str:
        return "divi5"
