"""
Translation Bridge v4 - Oxygen Builder JSON Converter.

Converts universal/parsed data TO Oxygen Builder JSON format.
Generates proper Oxygen structure with nested elements.
"""

from typing import Any, Dict, List, Optional
import secrets
import json


class OxygenConverter:
    """
    Converts parsed content to Oxygen Builder JSON format.

    Oxygen structure is a nested tree with each element containing:
    {
        "id": unique_id,
        "name": "element_name",
        "options": {},
        "children": []
    }
    """

    ELEMENT_TYPE_MAP = {
        "heading": "ct_headline",
        "text": "ct_text_block",
        "paragraph": "ct_text_block",
        "image": "ct_image",
        "button": "ct_link_button",
        "divider": "ct_div_block",
        "spacer": "ct_div_block",
        "icon": "ct_fancy_icon",
        "icon-box": "ct_icon_box",
        "video": "ct_video",
        "gallery": "ct_gallery",
        "carousel": "ct_slider",
        "tabs": "oxy_tabs",
        "accordion": "oxy_accordion",
        "testimonial": "oxy_testimonial",
        "counter": "oxy_counter",
        "progress": "oxy_progress_bar",
        "form": "ct_contact_form",
        "nav": "oxy_nav_menu",
        "menu": "oxy_nav_menu",
        "html": "ct_code_block",
        "section": "ct_section",
        "container": "ct_div_block",
        "column": "ct_div_block",
    }

    def __init__(self):
        self._id_counter = 0

    def convert(self, data: Any) -> str:
        """Convert universal data to Oxygen JSON string."""
        elements = self._convert_to_elements(data)
        return json.dumps({"ct_builder_json": {"ct_builder": elements}}, indent=2)

    def convert_to_dict(self, data: Any) -> Dict[str, Any]:
        """Convert universal data to Oxygen element structure."""
        elements = self._convert_to_elements(data)
        return {"ct_builder_json": {"ct_builder": elements}}

    def _convert_to_elements(self, data: Any) -> List[Dict[str, Any]]:
        """Convert data to Oxygen element structure."""
        if isinstance(data, dict):
            if "elements" in data:
                return [self._convert_element(el) for el in data["elements"]]
            return [self._convert_element(data)]

        elif isinstance(data, list):
            return [self._convert_element(item) for item in data if isinstance(item, dict)]

        return []

    def _convert_element(self, element: Dict[str, Any]) -> Dict[str, Any]:
        """Convert a single element and its children."""
        el_type = element.get("elType", element.get("type", ""))
        widget_type = element.get("widgetType", "")
        settings = element.get("settings", element.get("attributes", {}))
        children = element.get("elements", [])

        # Determine Oxygen element name
        if el_type == "section" or el_type == "container":
            return self._create_section(element)
        elif el_type == "column":
            return self._create_column(element)
        elif el_type == "widget" or widget_type:
            return self._create_widget(widget_type, settings)
        else:
            # Generic element
            return self._create_generic(element)

    def _create_section(self, element: Dict[str, Any]) -> Dict[str, Any]:
        """Create an Oxygen section element."""
        settings = element.get("settings", {})
        children = element.get("elements", [])

        options = {"ct_id": self._generate_id()}

        # Background
        bg_color = settings.get("background_color", "")
        if bg_color and not bg_color.startswith("globals"):
            options["background-color"] = bg_color

        # Padding
        padding = settings.get("padding", {})
        if isinstance(padding, dict):
            for side in ["top", "right", "bottom", "left"]:
                if padding.get(side):
                    options[f"padding-{side}"] = f"{padding[side]}{padding.get('unit', 'px')}"

        # Convert children
        converted_children = [self._convert_element(child) for child in children]

        return {
            "id": self._generate_id(),
            "name": "ct_section",
            "options": options,
            "children": converted_children,
        }

    def _create_column(self, element: Dict[str, Any]) -> Dict[str, Any]:
        """Create an Oxygen column/div element."""
        settings = element.get("settings", {})
        children = element.get("elements", [])

        options = {"ct_id": self._generate_id()}

        # Width
        col_size = settings.get("_column_size", 100)
        if col_size < 100:
            options["width"] = f"{col_size}%"

        # Convert children
        converted_children = [self._convert_element(child) for child in children]

        return {
            "id": self._generate_id(),
            "name": "ct_div_block",
            "options": options,
            "children": converted_children,
        }

    def _create_widget(self, widget_type: str, settings: Dict[str, Any]) -> Dict[str, Any]:
        """Create an Oxygen widget element."""
        element_name = self.ELEMENT_TYPE_MAP.get(widget_type, "ct_text_block")
        options = self._build_widget_options(widget_type, settings)
        options["ct_id"] = self._generate_id()

        return {
            "id": self._generate_id(),
            "name": element_name,
            "options": options,
            "children": [],
        }

    def _create_generic(self, element: Dict[str, Any]) -> Dict[str, Any]:
        """Create a generic Oxygen element."""
        comp_type = element.get("type", "text")
        element_name = self.ELEMENT_TYPE_MAP.get(comp_type, "ct_text_block")
        settings = element.get("attributes", element.get("settings", {}))
        content = element.get("content", "")

        options = self._build_widget_options(comp_type, settings, content)
        options["ct_id"] = self._generate_id()

        children = element.get("elements", [])
        converted_children = [self._convert_element(child) for child in children]

        return {
            "id": self._generate_id(),
            "name": element_name,
            "options": options,
            "children": converted_children,
        }

    def _build_widget_options(self, widget_type: str, settings: Dict[str, Any], content: str = "") -> Dict[str, Any]:
        """Build Oxygen options from widget settings."""
        options = {}

        if widget_type == "heading":
            options["ct_content"] = settings.get("title", "Heading")
            tag = settings.get("header_size", "h2")
            options["tag"] = tag

        elif widget_type in ["text", "text-editor"]:
            options["ct_content"] = content or settings.get("editor", settings.get("text", ""))

        elif widget_type == "image":
            image = settings.get("image", {})
            if isinstance(image, dict):
                options["src"] = image.get("url", "")
                options["alt"] = image.get("alt", "")

        elif widget_type == "button":
            options["ct_content"] = settings.get("text", "Click Here")
            link = settings.get("link", {})
            if isinstance(link, dict):
                options["url"] = link.get("url", "#")

        elif widget_type == "video":
            url = settings.get("youtube_url", settings.get("video_url", ""))
            options["embed_code"] = url
            options["video_type"] = "youtube"

        elif widget_type == "icon":
            icon = settings.get("selected_icon", {})
            if isinstance(icon, dict):
                options["icon"] = icon.get("value", "fas fa-star")

        elif widget_type == "icon-box":
            options["heading"] = settings.get("title_text", "")
            options["text"] = settings.get("description_text", "")
            icon = settings.get("selected_icon", {})
            if isinstance(icon, dict):
                options["icon"] = icon.get("value", "fas fa-star")

        elif widget_type == "counter":
            options["number_end"] = settings.get("ending_number", "100")
            options["prefix"] = settings.get("prefix", "")
            options["suffix"] = settings.get("suffix", "")
            options["title"] = settings.get("title", "")

        elif widget_type == "tabs":
            tabs = settings.get("tabs", [])
            options["tabs"] = [
                {"title": tab.get("tab_title", "Tab"), "content": tab.get("tab_content", "")}
                for tab in tabs
            ]

        elif widget_type == "accordion":
            items = settings.get("tabs", [])
            options["items"] = [
                {"title": item.get("tab_title", "Item"), "content": item.get("tab_content", "")}
                for item in items
            ]

        elif widget_type == "testimonial":
            options["testimonial"] = settings.get("testimonial_content", "")
            options["author"] = settings.get("testimonial_name", "")
            options["author_info"] = settings.get("testimonial_job", "")

        elif widget_type == "progress":
            percent = settings.get("percent", {})
            options["percentage"] = percent.get("size", 50) if isinstance(percent, dict) else 50
            options["title"] = settings.get("title", "")

        elif widget_type == "html":
            options["code"] = content or settings.get("html", "")

        elif widget_type in ["gallery", "carousel"]:
            gallery = settings.get("gallery", settings.get("wp_gallery", []))
            options["images"] = [
                {"url": img.get("url", ""), "id": img.get("id", "")}
                for img in gallery if isinstance(img, dict)
            ]

        # Common options
        if settings.get("align"):
            options["text-align"] = settings["align"]

        return options

    def _generate_id(self) -> int:
        """Generate unique Oxygen element ID."""
        self._id_counter += 1
        return self._id_counter

    def get_framework(self) -> str:
        return "oxygen"

    def get_supported_types(self) -> List[str]:
        return list(self.ELEMENT_TYPE_MAP.keys())
