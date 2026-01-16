"""
Translation Bridge v4 - Elementor JSON Converter.

Converts universal/parsed data TO Elementor JSON format.
Generates proper section > column > widget structure with all required settings.
"""

from typing import Any, Dict, List, Optional, Union
import secrets
from dataclasses import dataclass, field


@dataclass
class ElementorSettings:
    """Settings for Elementor conversion."""

    include_responsive: bool = True
    include_hover_states: bool = True
    default_font_family: str = "Poppins"
    version: str = "3.18.0"


class ElementorConverter:
    """
    Converts parsed content to Elementor JSON format.

    Generates proper Elementor structure:
    - Section (elType: section)
      - Column (elType: column)
        - Widget (elType: widget, widgetType: X)
    """

    # Universal type to Elementor widget mapping
    WIDGET_TYPE_MAP = {
        "heading": "heading",
        "text": "text-editor",
        "paragraph": "text-editor",
        "image": "image",
        "button": "button",
        "divider": "divider",
        "spacer": "spacer",
        "icon": "icon",
        "icon-box": "icon-box",
        "image-box": "image-box",
        "counter": "counter",
        "progress": "progress",
        "testimonial": "testimonial",
        "tabs": "tabs",
        "accordion": "accordion",
        "alert": "alert",
        "video": "video",
        "gallery": "image-gallery",
        "carousel": "image-carousel",
        "form": "form",
        "nav": "nav-menu",
        "menu": "nav-menu",
        "rating": "star-rating",
        "cta": "call-to-action",
        "html": "html",
    }

    def __init__(self, settings: Optional[ElementorSettings] = None):
        self.settings = settings or ElementorSettings()
        self._id_counter = 0

    def convert(self, data: Any) -> str:
        """
        Convert universal data to Elementor JSON string.

        Args:
            data: Universal component data or list of components

        Returns:
            Elementor JSON string
        """
        import json

        elements = self._convert_to_elements(data)
        return json.dumps(elements, indent=2)

    def convert_to_dict(self, data: Any) -> List[Dict[str, Any]]:
        """
        Convert universal data to Elementor element list.

        Args:
            data: Universal component data

        Returns:
            List of Elementor elements
        """
        return self._convert_to_elements(data)

    def _convert_to_elements(self, data: Any) -> List[Dict[str, Any]]:
        """Convert data to Elementor element structure."""
        if isinstance(data, dict):
            # Check if it's already in Elementor format
            if "elType" in data:
                return [data]

            # Check if it has elements array
            if "elements" in data:
                return [self._convert_element(el) for el in data["elements"]]

            # Single component
            return [self._wrap_in_section(self._convert_component(data))]

        elif isinstance(data, list):
            # List of components - wrap each in section/column
            sections = []
            for item in data:
                if isinstance(item, dict):
                    if item.get("elType") == "section":
                        sections.append(item)
                    else:
                        widget = self._convert_component(item)
                        sections.append(self._wrap_in_section(widget))
            return sections

        return []

    def _convert_element(self, element: Dict[str, Any]) -> Dict[str, Any]:
        """Convert a single element, preserving structure if already Elementor."""
        if "elType" in element:
            # Already Elementor format, just ensure IDs
            return self._ensure_ids(element)

        # Convert to Elementor widget
        return self._convert_component(element)

    def _convert_component(self, component: Dict[str, Any]) -> Dict[str, Any]:
        """Convert a universal component to Elementor widget."""
        comp_type = component.get("type", component.get("widgetType", "text"))
        widget_type = self.WIDGET_TYPE_MAP.get(comp_type, "text-editor")

        settings = self._build_settings(component, widget_type)

        return {
            "id": self._generate_id(),
            "elType": "widget",
            "widgetType": widget_type,
            "settings": settings,
        }

    def _build_settings(self, component: Dict[str, Any], widget_type: str) -> Dict[str, Any]:
        """Build Elementor settings from component data."""
        settings = {}

        # Get raw content/attributes
        content = component.get("content", "")
        attrs = component.get("attributes", component.get("settings", {}))

        # Widget-specific settings
        if widget_type == "heading":
            settings["title"] = content or attrs.get("title", "Heading")
            settings["header_size"] = f"h{attrs.get('level', 2)}"
            if attrs.get("alignment"):
                settings["align"] = attrs["alignment"]

        elif widget_type == "text-editor":
            settings["editor"] = content or attrs.get("text", "")

        elif widget_type == "button":
            settings["text"] = content or attrs.get("label", "Click Here")
            if attrs.get("url"):
                settings["link"] = self._create_link(attrs.get("url"), attrs.get("target", "_self"))
            settings["button_type"] = attrs.get("variant", "default")

        elif widget_type == "image":
            settings["image"] = {
                "url": attrs.get("image_url", attrs.get("url", "")),
                "id": "",
                "alt": attrs.get("alt_text", ""),
            }

        elif widget_type == "icon":
            icon_value = attrs.get("icon", "fas fa-star")
            settings["selected_icon"] = {
                "value": icon_value,
                "library": "fa-solid" if icon_value.startswith("fas") else "fa-regular",
            }

        elif widget_type == "icon-box":
            settings["title_text"] = attrs.get("title", content)
            settings["description_text"] = attrs.get("description", "")
            icon_value = attrs.get("icon", "fas fa-star")
            settings["selected_icon"] = {
                "value": icon_value,
                "library": "fa-solid",
            }

        elif widget_type == "counter":
            settings["ending_number"] = attrs.get("number", "100")
            settings["title"] = attrs.get("title", "")
            settings["prefix"] = attrs.get("prefix", "")
            settings["suffix"] = attrs.get("suffix", "")

        elif widget_type == "tabs":
            tabs = attrs.get("tabs", [])
            settings["tabs"] = [
                {
                    "_id": self._generate_id(),
                    "tab_title": tab.get("title", f"Tab {i+1}"),
                    "tab_content": tab.get("content", ""),
                }
                for i, tab in enumerate(tabs)
            ]

        elif widget_type == "accordion":
            items = attrs.get("items", attrs.get("tabs", []))
            settings["tabs"] = [
                {
                    "_id": self._generate_id(),
                    "tab_title": item.get("title", f"Item {i+1}"),
                    "tab_content": item.get("content", ""),
                }
                for i, item in enumerate(items)
            ]

        elif widget_type == "video":
            settings["video_type"] = "youtube"
            settings["youtube_url"] = attrs.get("url", attrs.get("video_url", ""))

        elif widget_type == "image-gallery":
            gallery = attrs.get("gallery", [])
            settings["gallery"] = [
                {"id": img.get("id", ""), "url": img.get("url", "")}
                for img in gallery
            ]

        elif widget_type == "html":
            settings["html"] = content or attrs.get("html", "")

        # Add common styling settings
        settings = self._add_styling_settings(settings, attrs)

        return settings

    def _add_styling_settings(self, settings: Dict[str, Any], attrs: Dict[str, Any]) -> Dict[str, Any]:
        """Add common styling settings."""
        # Typography
        if attrs.get("font_family"):
            settings["typography_typography"] = "custom"
            settings["typography_font_family"] = attrs["font_family"]

        if attrs.get("font_size"):
            settings["typography_font_size"] = self._create_size_value(attrs["font_size"])

        if attrs.get("font_weight"):
            settings["typography_font_weight"] = str(attrs["font_weight"])

        # Colors
        if attrs.get("color"):
            settings["title_color"] = attrs["color"]

        if attrs.get("background_color"):
            settings["background_color"] = attrs["background_color"]

        # Spacing
        if attrs.get("margin"):
            settings["_margin"] = self._create_spacing_value(attrs["margin"])

        if attrs.get("padding"):
            settings["_padding"] = self._create_spacing_value(attrs["padding"])

        # Alignment
        if attrs.get("alignment") or attrs.get("align"):
            settings["align"] = attrs.get("alignment", attrs.get("align"))

        return settings

    def _wrap_in_section(self, widget: Dict[str, Any]) -> Dict[str, Any]:
        """Wrap a widget in section > column structure."""
        column = {
            "id": self._generate_id(),
            "elType": "column",
            "settings": {
                "_column_size": 100,
                "_inline_size": None,
            },
            "elements": [widget],
        }

        return {
            "id": self._generate_id(),
            "elType": "section",
            "settings": {},
            "elements": [column],
        }

    def _create_link(self, url: str, target: str = "_self") -> Dict[str, Any]:
        """Create Elementor link object."""
        return {
            "url": url,
            "is_external": "on" if target == "_blank" else "",
            "nofollow": "",
            "custom_attributes": "",
        }

    def _create_size_value(self, value: Any) -> Dict[str, Any]:
        """Create Elementor size value object."""
        if isinstance(value, dict):
            return value

        # Parse string value like "16px"
        if isinstance(value, str):
            import re
            match = re.match(r"^([\d.]+)(px|em|rem|%)?$", value)
            if match:
                return {
                    "size": float(match.group(1)),
                    "unit": match.group(2) or "px",
                }

        return {"size": float(value) if value else 0, "unit": "px"}

    def _create_spacing_value(self, value: Any) -> Dict[str, Any]:
        """Create Elementor spacing value object."""
        if isinstance(value, dict):
            return {
                "top": str(value.get("top", "0")),
                "right": str(value.get("right", "0")),
                "bottom": str(value.get("bottom", "0")),
                "left": str(value.get("left", "0")),
                "unit": value.get("unit", "px"),
                "isLinked": False,
            }

        return {
            "top": "0",
            "right": "0",
            "bottom": "0",
            "left": "0",
            "unit": "px",
            "isLinked": True,
        }

    def _ensure_ids(self, element: Dict[str, Any]) -> Dict[str, Any]:
        """Ensure all elements have IDs."""
        if "id" not in element or not element["id"]:
            element["id"] = self._generate_id()

        if "elements" in element:
            element["elements"] = [self._ensure_ids(el) for el in element["elements"]]

        return element

    def _generate_id(self) -> str:
        """Generate unique Elementor ID (8-char hex)."""
        return secrets.token_hex(4)

    def get_framework(self) -> str:
        """Return framework name."""
        return "elementor"

    def get_supported_types(self) -> List[str]:
        """Return list of supported widget types."""
        return list(self.WIDGET_TYPE_MAP.keys())
