"""
Translation Bridge v4 - Elementor JSON Parser.

Parses Elementor page builder JSON format and extracts structured data
for transformation via the Zone Theory engine.
"""

import json
from dataclasses import dataclass, field
from typing import Any, Dict, List, Optional
from pathlib import Path

from ..transforms.registry import ParserRegistry
from ..transforms.core import TransformEngine, Zone, ZoneType


@dataclass
class ElementorElement:
    """Represents a parsed Elementor element."""

    id: str
    el_type: str                            # section, column, widget
    widget_type: Optional[str] = None       # heading, text-editor, image, etc.
    settings: Dict[str, Any] = field(default_factory=dict)
    elements: List["ElementorElement"] = field(default_factory=list)
    is_inner: bool = False

    def to_dict(self) -> Dict[str, Any]:
        """Convert to dictionary format."""
        result = {
            "id": self.id,
            "elType": self.el_type,
            "settings": self.settings,
            "elements": [el.to_dict() for el in self.elements],
        }
        if self.widget_type:
            result["widgetType"] = self.widget_type
        if self.is_inner:
            result["isInner"] = self.is_inner
        return result


@dataclass
class ElementorDocument:
    """Represents a parsed Elementor document."""

    elements: List[ElementorElement]
    version: str = ""
    title: str = ""
    meta: Dict[str, Any] = field(default_factory=dict)

    def to_dict(self) -> Dict[str, Any]:
        """Convert to dictionary format."""
        return {
            "elements": [el.to_dict() for el in self.elements],
            "version": self.version,
            "title": self.title,
            "meta": self.meta,
        }


@ParserRegistry.register(
    name="elementor_parser",
    framework="elementor",
    description="Parse Elementor page builder JSON format",
    version="4.1.0",
    file_extensions=[".json"],
)
class ElementorParser:
    """
    Parser for Elementor page builder JSON format.

    Handles both exported Elementor JSON files and post meta content.
    Supports all standard Elementor elements and most third-party widgets.
    """

    # Widget type mappings for common Elementor widgets
    WIDGET_TYPES = {
        "heading": {"content_key": "title", "type": "text"},
        "text-editor": {"content_key": "editor", "type": "html"},
        "image": {"content_key": "image", "type": "media"},
        "video": {"content_key": "video", "type": "media"},
        "button": {"content_key": "text", "type": "text"},
        "icon": {"content_key": "icon", "type": "icon"},
        "icon-box": {"content_key": "title_text", "type": "text"},
        "image-box": {"content_key": "title_text", "type": "text"},
        "counter": {"content_key": "title", "type": "text"},
        "progress": {"content_key": "title", "type": "text"},
        "testimonial": {"content_key": "testimonial_content", "type": "html"},
        "tabs": {"content_key": "tabs", "type": "repeater"},
        "accordion": {"content_key": "tabs", "type": "repeater"},
        "toggle": {"content_key": "tabs", "type": "repeater"},
        "social-icons": {"content_key": "social_icon_list", "type": "repeater"},
        "alert": {"content_key": "alert_description", "type": "html"},
        "html": {"content_key": "html", "type": "html"},
        "shortcode": {"content_key": "shortcode", "type": "text"},
        "divider": {"content_key": None, "type": "structural"},
        "spacer": {"content_key": None, "type": "structural"},
        "google_maps": {"content_key": "address", "type": "text"},
        "form": {"content_key": "form_fields", "type": "repeater"},
        "nav-menu": {"content_key": "menu", "type": "select"},
        "sidebar": {"content_key": "sidebar", "type": "select"},
    }

    def __init__(self):
        self.engine = TransformEngine()

    def parse_file(self, file_path: str) -> ElementorDocument:
        """
        Parse an Elementor JSON file.

        Args:
            file_path: Path to the JSON file

        Returns:
            ElementorDocument with parsed elements
        """
        path = Path(file_path)
        if not path.exists():
            raise FileNotFoundError(f"File not found: {file_path}")

        with open(path, "r", encoding="utf-8") as f:
            data = json.load(f)

        return self.parse(data)

    def parse(self, data: Any) -> ElementorDocument:
        """
        Parse Elementor JSON data.

        Args:
            data: JSON data (dict or list)

        Returns:
            ElementorDocument with parsed elements
        """
        # Handle different Elementor export formats
        if isinstance(data, dict):
            # Check for wrapped format
            if "content" in data:
                elements_data = data["content"]
            elif "elements" in data:
                elements_data = data["elements"]
            else:
                elements_data = [data]

            version = data.get("version", "")
            title = data.get("title", "")
            meta = {k: v for k, v in data.items() if k not in ["content", "elements", "version", "title"]}
        elif isinstance(data, list):
            elements_data = data
            version = ""
            title = ""
            meta = {}
        else:
            raise ValueError(f"Unexpected data type: {type(data)}")

        elements = [self._parse_element(el) for el in elements_data]

        return ElementorDocument(
            elements=elements,
            version=version,
            title=title,
            meta=meta,
        )

    def _parse_element(self, data: Dict[str, Any]) -> ElementorElement:
        """Parse a single Elementor element."""
        element = ElementorElement(
            id=data.get("id", data.get("_id", "")),
            el_type=data.get("elType", "widget"),
            widget_type=data.get("widgetType"),
            settings=data.get("settings", {}),
            is_inner=data.get("isInner", False),
        )

        # Parse child elements
        children = data.get("elements", [])
        element.elements = [self._parse_element(child) for child in children]

        return element

    def extract_content(self, doc: ElementorDocument) -> List[Dict[str, Any]]:
        """
        Extract all translatable content from an Elementor document.

        Args:
            doc: Parsed ElementorDocument

        Returns:
            List of content items with paths and values
        """
        content_items = []

        def extract_from_element(element: ElementorElement, path: str):
            widget_info = self.WIDGET_TYPES.get(element.widget_type or "", {})
            content_key = widget_info.get("content_key")

            if content_key and content_key in element.settings:
                value = element.settings[content_key]
                if value:
                    content_items.append({
                        "path": f"{path}.settings.{content_key}",
                        "key": content_key,
                        "value": value,
                        "widget_type": element.widget_type,
                        "content_type": widget_info.get("type", "text"),
                    })

            # Also check common content keys in settings
            for key in ["title", "description", "text", "content", "editor", "heading"]:
                if key in element.settings and key != content_key:
                    value = element.settings[key]
                    if value and isinstance(value, str) and value.strip():
                        content_items.append({
                            "path": f"{path}.settings.{key}",
                            "key": key,
                            "value": value,
                            "widget_type": element.widget_type,
                            "content_type": "text",
                        })

            # Recurse into children
            for i, child in enumerate(element.elements):
                child_path = f"{path}.elements[{i}]"
                extract_from_element(child, child_path)

        for i, element in enumerate(doc.elements):
            extract_from_element(element, f"elements[{i}]")

        return content_items

    def analyze(self, doc: ElementorDocument) -> Dict[str, Any]:
        """
        Analyze an Elementor document and return statistics.

        Args:
            doc: Parsed ElementorDocument

        Returns:
            Dictionary with analysis results
        """
        stats = {
            "total_elements": 0,
            "sections": 0,
            "columns": 0,
            "widgets": 0,
            "widget_types": {},
            "content_items": 0,
        }

        def analyze_element(element: ElementorElement):
            stats["total_elements"] += 1

            if element.el_type == "section":
                stats["sections"] += 1
            elif element.el_type == "column":
                stats["columns"] += 1
            elif element.el_type == "widget":
                stats["widgets"] += 1
                wt = element.widget_type or "unknown"
                stats["widget_types"][wt] = stats["widget_types"].get(wt, 0) + 1

            for child in element.elements:
                analyze_element(child)

        for element in doc.elements:
            analyze_element(element)

        # Count content items
        content = self.extract_content(doc)
        stats["content_items"] = len(content)

        # Use Zone Theory analysis
        raw_data = doc.to_dict()
        zone_analysis = self.engine.analyze(raw_data.get("elements", []))

        return {
            **stats,
            "zones": zone_analysis,
        }

    def to_json(self, doc: ElementorDocument, indent: int = 2) -> str:
        """Serialize document to JSON string."""
        return json.dumps(doc.to_dict(), indent=indent, ensure_ascii=False)
