"""
Shared universal-document primitives for JSON source parsers.

Every source parser normalizes its framework's native JSON into the
"universal" Elementor-shaped element dict the v4 converters consume::

    {
        "id": "abc123",
        "elType": "section" | "column" | "container" | "widget",
        "widgetType": "heading",          # widgets only
        "settings": {...},                # Elementor-style setting keys
        "elements": [ <children> ],
        "isInner": True,                  # only when true
        "responsive": {...},              # optional canonical responsive data
    }

``UniversalElement``/``UniversalDocument`` provide the dataclasses plus the
``to_dict`` / ``analyze`` / ``extract_content`` surface the CLI expects from
a parser's output.
"""

from __future__ import annotations

from dataclasses import dataclass, field
from typing import Any, Dict, List, Optional


@dataclass
class UniversalElement:
    """A single element in the universal (Elementor-shaped) tree."""

    id: str
    el_type: str = "widget"
    widget_type: str = ""
    settings: Dict[str, Any] = field(default_factory=dict)
    elements: List["UniversalElement"] = field(default_factory=list)
    is_inner: bool = False
    responsive: Optional[Dict[str, Any]] = None

    def to_dict(self) -> Dict[str, Any]:
        data: Dict[str, Any] = {
            "id": self.id,
            "elType": self.el_type,
            "settings": self.settings,
            "elements": [el.to_dict() for el in self.elements],
        }
        if self.widget_type:
            data["widgetType"] = self.widget_type
        if self.is_inner:
            data["isInner"] = True
        if self.responsive:
            data["responsive"] = self.responsive
        return data


@dataclass
class UniversalDocument:
    """A parsed document: a list of root universal elements plus metadata."""

    elements: List[UniversalElement] = field(default_factory=list)
    version: str = ""
    title: str = ""
    meta: Dict[str, Any] = field(default_factory=dict)

    def to_dict(self) -> Dict[str, Any]:
        return {
            "elements": [el.to_dict() for el in self.elements],
            "version": self.version,
            "title": self.title,
            "meta": self.meta,
        }


def analyze_document(doc: UniversalDocument) -> Dict[str, Any]:
    """Structure statistics in the shape the CLI's analyze command prints."""
    stats = {"sections": 0, "columns": 0, "widgets": 0, "widget_types": {}}

    def walk(element: UniversalElement) -> None:
        if element.el_type in ("section", "container"):
            stats["sections"] += 1
        elif element.el_type == "column":
            stats["columns"] += 1
        else:
            stats["widgets"] += 1
            if element.widget_type:
                stats["widget_types"][element.widget_type] = (
                    stats["widget_types"].get(element.widget_type, 0) + 1
                )
        for child in element.elements:
            walk(child)

    for element in doc.elements:
        walk(element)
    return stats


# Setting keys that carry human-readable content, in extraction order.
_CONTENT_KEYS = ("title", "editor", "text", "html", "testimonial_content")


def extract_document_content(doc: UniversalDocument) -> Dict[str, List[str]]:
    """Collect human-readable content strings keyed by widget type."""
    content: Dict[str, List[str]] = {}

    def walk(element: UniversalElement) -> None:
        if element.widget_type:
            for key in _CONTENT_KEYS:
                value = element.settings.get(key)
                if isinstance(value, str) and value.strip():
                    content.setdefault(element.widget_type, []).append(value)
                    break
        for child in element.elements:
            walk(child)

    for element in doc.elements:
        walk(element)
    return content
