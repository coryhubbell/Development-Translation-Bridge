"""
Translation Bridge v4 - Elementor 4 Atomic Editor JSON Source Parser.

Parses Elementor 4 "atomic" element trees into the universal element shape
so Atomic Editor content can ride the lossless ``transform`` path.

The atomic format was verified against the open-source elementor repo
(v4.4.0): nodes are ``{id, version, elType: "e-*", isInner, interactions,
settings, editor_settings, styles, elements}``, and every stored setting is
wrapped in a typed-prop envelope ``{"$$type": <key>, "value": ...}`` —
``html-v3`` content props nest a ``string`` prop under ``content``, links
store ``destination``/``isTargetBlank``, images nest ``src -> {id, url,
alt}``. Style definitions carry ``variants`` with per-breakpoint/state
props, which canonicalize into the shared responsive model.

Accepted input shapes: a bare node list, a single node, or the template
envelope ``{"content": [...], "version": "0.4", ...}``.
"""

from __future__ import annotations

import json
from typing import Any, Dict, List, Optional, Union

from translation_bridge.parsers.universal import (
    UniversalDocument,
    UniversalElement,
    analyze_document,
    extract_document_content,
)
from translation_bridge.responsive import ELEMENTOR_BREAKPOINTS, STATES
from translation_bridge.transforms.registry import ParserRegistry


# Atomic elType -> (elType, widgetType) in the universal shape.
_CONTAINER_TYPES = {
    "e-div-block": "container",
    "e-flexbox": "container",
    "e-grid": "container",
}

_WIDGET_TYPES = {
    "e-heading": "heading",
    "e-paragraph": "text-editor",
    "e-button": "button",
    "e-image": "image",
    "e-svg": "icon",
    "e-divider": "divider",
    "e-youtube": "video",
    "e-self-hosted-video": "video",
    "e-form": "form",
}


def _unwrap(value: Any) -> Any:
    """Recursively unwrap Elementor's typed-prop envelopes.

    ``{"$$type": "html-v3", "value": {"content": {"$$type": "string",
    "value": "Hi"}}}`` unwraps to ``"Hi"``; object shapes (link destination,
    image src) unwrap member-wise. Plain values pass through.
    """
    if not isinstance(value, dict):
        return value

    if "$$type" in value and "value" in value:
        inner = value["value"]
        if value["$$type"] == "html-v3" and isinstance(inner, dict) and "content" in inner:
            return _unwrap(inner["content"])
        return _unwrap(inner)

    return {key: _unwrap(entry) for key, entry in value.items()}


@ParserRegistry.register(
    name="elementor4_parser",
    framework="elementor4",
    description="Elementor 4 Atomic Editor parser (typed-prop schema)",
    version="4.7.0",
    file_extensions=[".json"],
)
class Elementor4Parser:
    """Parse Elementor 4 Atomic JSON into a UniversalDocument."""

    def parse_file(self, file_path: str) -> UniversalDocument:
        with open(file_path, "r", encoding="utf-8") as handle:
            return self.parse(json.load(handle))

    def parse(self, data: Union[str, Dict[str, Any], List[Any]]) -> UniversalDocument:
        if isinstance(data, str):
            data = json.loads(data)

        version = ""
        title = ""

        if isinstance(data, dict):
            version = str(data.get("version", ""))
            title = str(data.get("title", ""))
            if isinstance(data.get("content"), list):
                nodes = data["content"]
            elif isinstance(data.get("elements"), list):
                nodes = data["elements"]
            elif "elType" in data:
                nodes = [data]
            else:
                nodes = []
        elif isinstance(data, list):
            nodes = data
        else:
            nodes = []

        roots = [
            el
            for node in nodes
            if isinstance(node, dict) and (el := self._parse_node(node, False))
        ]
        return UniversalDocument(
            elements=roots,
            version=version,
            title=title,
            meta={"source_framework": "elementor4"},
        )

    # ------------------------------------------------------------------
    # Node normalization
    # ------------------------------------------------------------------

    def _parse_node(self, node: Dict[str, Any], is_inner: bool) -> Optional[UniversalElement]:
        el_type = str(node.get("elType", ""))
        if not el_type:
            return None

        settings = node.get("settings") if isinstance(node.get("settings"), dict) else {}
        styles = node.get("styles") if isinstance(node.get("styles"), dict) else {}
        element_id = str(node.get("id", ""))
        responsive = self._canonicalize_styles(styles)

        if el_type in _CONTAINER_TYPES:
            element = UniversalElement(
                id=element_id,
                el_type=_CONTAINER_TYPES[el_type],
                settings={},
                is_inner=is_inner or bool(node.get("isInner")),
                responsive=responsive,
            )
        else:
            widget_type = _WIDGET_TYPES.get(el_type, "text-editor")
            element = UniversalElement(
                id=element_id,
                el_type="widget",
                widget_type=widget_type,
                settings=self._widget_settings(el_type, widget_type, settings),
                is_inner=is_inner or bool(node.get("isInner")),
                responsive=responsive,
            )

        for child in node.get("elements") or []:
            if isinstance(child, dict):
                child_element = self._parse_node(child, True)
                if child_element:
                    element.elements.append(child_element)

        return element

    def _widget_settings(
        self, el_type: str, widget_type: str, settings: Dict[str, Any]
    ) -> Dict[str, Any]:
        plain = {key: _unwrap(value) for key, value in settings.items()}
        out: Dict[str, Any] = {}

        if widget_type == "heading":
            out["title"] = self._as_text(plain.get("title", ""))
            tag = plain.get("tag", "h2")
            out["header_size"] = tag if isinstance(tag, str) else "h2"
        elif widget_type == "text-editor":
            out["editor"] = self._as_text(plain.get("paragraph", plain.get("text", "")))
        elif widget_type == "button":
            out["text"] = self._as_text(plain.get("text", ""))
            link = plain.get("link")
            if isinstance(link, dict):
                url = link.get("destination", link.get("url", ""))
                if isinstance(url, str) and url:
                    out["link"] = {"url": url}
                    if link.get("isTargetBlank"):
                        out["link"]["is_external"] = "on"
        elif widget_type == "image":
            image = plain.get("image")
            if isinstance(image, dict):
                src = image.get("src") if isinstance(image.get("src"), dict) else image
                out["image"] = {"url": self._as_text(src.get("url", ""))}
                if src.get("id"):
                    out["image"]["id"] = src["id"]
                if src.get("alt"):
                    out["image"]["alt"] = self._as_text(src["alt"])
        elif widget_type == "icon":
            svg = plain.get("svg")
            if isinstance(svg, str) and svg:
                out["selected_icon"] = {"value": svg}
        elif widget_type == "video":
            url = plain.get("url", plain.get("source", ""))
            if isinstance(url, str) and url:
                out["youtube_url"] = url

        # Pass through any remaining plain scalars not already handled.
        handled = {"title", "tag", "paragraph", "text", "link", "image", "svg", "url",
                   "source", "classes", "attributes"}
        for key, value in plain.items():
            if key in handled or key in out:
                continue
            if isinstance(value, (str, int, float, bool)):
                out[key] = value

        return out

    @staticmethod
    def _as_text(value: Any) -> str:
        return value if isinstance(value, str) else ""

    # ------------------------------------------------------------------
    # Responsive styles
    # ------------------------------------------------------------------

    def _canonicalize_styles(self, styles: Dict[str, Any]) -> Optional[Dict[str, Any]]:
        """Merge style-definition variants into the canonical responsive shape."""
        canonical: Dict[str, Dict[str, Dict[str, Any]]] = {}

        for definition in styles.values():
            if not isinstance(definition, dict):
                continue
            for variant in definition.get("variants") or []:
                if not isinstance(variant, dict) or not isinstance(variant.get("props"), dict):
                    continue
                meta = variant.get("meta") if isinstance(variant.get("meta"), dict) else {}
                breakpoint = ELEMENTOR_BREAKPOINTS.get(meta.get("breakpoint") or "desktop")
                state = "default" if meta.get("state") is None else str(meta["state"])
                if breakpoint is None or state not in STATES:
                    continue
                bucket = canonical.setdefault(breakpoint, {}).setdefault(state, {})
                bucket.update(variant["props"])

        return {"styles": canonical} if canonical else None

    # ------------------------------------------------------------------
    # CLI surface
    # ------------------------------------------------------------------

    def extract_content(self, doc: UniversalDocument) -> Dict[str, List[str]]:
        return extract_document_content(doc)

    def analyze(self, doc: UniversalDocument) -> Dict[str, Any]:
        return analyze_document(doc)

    def get_framework(self) -> str:
        return "elementor4"
