"""
Translation Bridge v4 - Bricks Builder JSON Converter.

Converts universal/parsed data TO Bricks Builder JSON format.
Generates proper Bricks structure with elements and settings.
"""

from typing import Any, Dict, List, Optional
import secrets
import json


class BricksConverter:
    """
    Converts parsed content to Bricks Builder JSON format.

    Bricks structure:
    {
        "id": "unique-id",
        "name": "element-name",
        "parent": 0 or parent-id,
        "children": [],
        "settings": {}
    }
    """

    # Universal type to Bricks element mapping
    ELEMENT_TYPE_MAP = {
        "heading": "heading",
        "text": "text-basic",
        "paragraph": "text-basic",
        "image": "image",
        "button": "button",
        "divider": "divider",
        "spacer": "divider",
        "icon": "icon",
        "icon-box": "icon-box",
        "video": "video",
        "gallery": "image-gallery",
        "carousel": "carousel",
        "tabs": "tabs",
        "accordion": "accordion",
        "testimonial": "testimonials",
        "counter": "counter",
        "progress": "progress-bar",
        "form": "form",
        "nav": "nav-menu",
        "menu": "nav-menu",
        "html": "code",
        "section": "section",
        "container": "container",
        "column": "div",
    }

    def __init__(self):
        self._id_counter = 0

    def convert(self, data: Any) -> str:
        """
        Convert universal data to Bricks JSON string.

        Args:
            data: Universal component data or list of components

        Returns:
            Bricks JSON string
        """
        elements = self._convert_to_elements(data)
        return json.dumps(elements, indent=2)

    def convert_to_dict(self, data: Any) -> List[Dict[str, Any]]:
        """
        Convert universal data to Bricks element list.

        Args:
            data: Universal component data

        Returns:
            List of Bricks elements
        """
        return self._convert_to_elements(data)

    def _convert_to_elements(self, data: Any, parent: int = 0) -> List[Dict[str, Any]]:
        """Convert data to Bricks element structure."""
        elements = []

        if isinstance(data, dict):
            if "elements" in data:
                for el in data["elements"]:
                    elements.extend(self._convert_element(el, parent))
            else:
                elements.extend(self._convert_element(data, parent))

        elif isinstance(data, list):
            for item in data:
                if isinstance(item, dict):
                    elements.extend(self._convert_element(item, parent))

        return elements

    def _convert_element(self, element: Dict[str, Any], parent: int = 0) -> List[Dict[str, Any]]:
        """Convert a single element and its children."""
        el_type = element.get("elType", element.get("type", ""))
        widget_type = element.get("widgetType", "")
        settings = element.get("settings", element.get("attributes", {}))
        children = element.get("elements", [])

        elements = []

        if el_type == "section" or el_type == "container":
            section_el = self._create_section(element, parent)
            elements.append(section_el)

            # Convert children with section as parent
            for child in children:
                elements.extend(self._convert_element(child, section_el["id"]))

        elif el_type == "column":
            column_el = self._create_container(element, parent)
            elements.append(column_el)

            # Convert children with column as parent
            for child in children:
                elements.extend(self._convert_element(child, column_el["id"]))

        elif el_type == "widget" or widget_type:
            widget_el = self._create_widget(element, parent)
            elements.append(widget_el)

        else:
            # Generic element
            generic_el = self._create_generic(element, parent)
            elements.append(generic_el)

            for child in children:
                elements.extend(self._convert_element(child, generic_el["id"]))

        return elements

    def _create_section(self, element: Dict[str, Any], parent: int) -> Dict[str, Any]:
        """Create a Bricks section element."""
        settings = element.get("settings", {})

        bricks_settings = {
            "tag": "section",
        }

        # Background
        bg_color = settings.get("background_color", "")
        if bg_color and not bg_color.startswith("globals"):
            bricks_settings["_background"] = {"color": {"hex": bg_color}}

        # Padding
        padding = settings.get("padding", {})
        if isinstance(padding, dict) and any(padding.get(s) for s in ["top", "right", "bottom", "left"]):
            bricks_settings["_padding"] = {
                "top": f"{padding.get('top', 0)}{padding.get('unit', 'px')}",
                "right": f"{padding.get('right', 0)}{padding.get('unit', 'px')}",
                "bottom": f"{padding.get('bottom', 0)}{padding.get('unit', 'px')}",
                "left": f"{padding.get('left', 0)}{padding.get('unit', 'px')}",
            }

        return {
            "id": self._generate_id(),
            "name": "section",
            "parent": parent,
            "children": [],
            "settings": bricks_settings,
        }

    def _create_container(self, element: Dict[str, Any], parent: int) -> Dict[str, Any]:
        """Create a Bricks container/div element."""
        settings = element.get("settings", {})

        bricks_settings = {
            "tag": "div",
        }

        # Width
        col_size = settings.get("_column_size", 100)
        if col_size < 100:
            bricks_settings["_width"] = f"{col_size}%"

        return {
            "id": self._generate_id(),
            "name": "container",
            "parent": parent,
            "children": [],
            "settings": bricks_settings,
        }

    def _create_widget(self, element: Dict[str, Any], parent: int) -> Dict[str, Any]:
        """Create a Bricks widget element."""
        widget_type = element.get("widgetType", element.get("type", "text"))
        settings = element.get("settings", element.get("attributes", {}))

        bricks_name = self.ELEMENT_TYPE_MAP.get(widget_type, "text-basic")
        bricks_settings = self._build_widget_settings(widget_type, settings)

        return {
            "id": self._generate_id(),
            "name": bricks_name,
            "parent": parent,
            "children": [],
            "settings": bricks_settings,
        }

    def _create_generic(self, element: Dict[str, Any], parent: int) -> Dict[str, Any]:
        """Create a generic Bricks element."""
        comp_type = element.get("type", "div")
        bricks_name = self.ELEMENT_TYPE_MAP.get(comp_type, "div")

        return {
            "id": self._generate_id(),
            "name": bricks_name,
            "parent": parent,
            "children": [],
            "settings": {},
        }

    def _build_widget_settings(self, widget_type: str, settings: Dict[str, Any]) -> Dict[str, Any]:
        """Build Bricks settings from widget settings."""
        bricks_settings = {}

        if widget_type == "heading":
            bricks_settings["text"] = settings.get("title", "Heading")
            bricks_settings["tag"] = settings.get("header_size", "h2")

        elif widget_type in ["text", "text-editor"]:
            bricks_settings["text"] = settings.get("editor", settings.get("text", ""))

        elif widget_type == "image":
            image = settings.get("image", {})
            if isinstance(image, dict):
                bricks_settings["image"] = {
                    "url": image.get("url", ""),
                    "id": image.get("id", ""),
                }

        elif widget_type == "button":
            bricks_settings["text"] = settings.get("text", "Click Here")
            link = settings.get("link", {})
            if isinstance(link, dict):
                bricks_settings["link"] = {
                    "url": link.get("url", "#"),
                    "type": "external",
                }

        elif widget_type == "video":
            bricks_settings["videoType"] = "youtube"
            bricks_settings["youtubeId"] = self._extract_youtube_id(settings.get("youtube_url", ""))

        elif widget_type == "icon":
            icon = settings.get("selected_icon", {})
            if isinstance(icon, dict):
                bricks_settings["icon"] = {"icon": icon.get("value", "fas fa-star")}

        elif widget_type == "counter":
            bricks_settings["countTo"] = settings.get("ending_number", "100")
            bricks_settings["title"] = settings.get("title", "")

        elif widget_type == "tabs":
            tabs = settings.get("tabs", [])
            bricks_settings["tabs"] = [
                {
                    "title": tab.get("tab_title", f"Tab {i+1}"),
                    "content": tab.get("tab_content", ""),
                }
                for i, tab in enumerate(tabs)
            ]

        elif widget_type == "accordion":
            items = settings.get("tabs", [])
            bricks_settings["items"] = [
                {
                    "title": item.get("tab_title", f"Item {i+1}"),
                    "content": item.get("tab_content", ""),
                }
                for i, item in enumerate(items)
            ]

        # Common styling
        if settings.get("align"):
            bricks_settings["_textAlign"] = settings["align"]

        return bricks_settings

    def _extract_youtube_id(self, url: str) -> str:
        """Extract YouTube video ID from URL."""
        if "v=" in url:
            return url.split("v=")[1].split("&")[0]
        elif "youtu.be" in url:
            return url.split("/")[-1]
        return url

    def _generate_id(self) -> str:
        """Generate unique Bricks element ID."""
        return secrets.token_hex(3)

    def get_framework(self) -> str:
        """Return framework name."""
        return "bricks"

    def get_supported_types(self) -> List[str]:
        """Return list of supported element types."""
        return list(self.ELEMENT_TYPE_MAP.keys())
