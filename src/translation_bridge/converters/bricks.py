"""
Translation Bridge v4 - Bricks Builder JSON Converter.

Converts universal/parsed data TO Bricks Builder JSON format.
Generates proper Bricks structure with elements and settings.
"""

from typing import Any, Dict, List, Optional
import secrets
import json

from translation_bridge.responsive import (
    canonical_to_bricks_settings,
    element_responsive,
)


# Upstream framework version this converter is calibrated against.
TARGET_CMS_VERSION: str = "2.3.5"


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
        "call-to-action": "icon-box",
        "price-table": "pricing-tables",
        "alert": "alert",
        "blockquote": "text-basic",
        "icon-list": "list",
        "image-gallery": "image-gallery",
        "social-icons": "social-icons",
        "text-editor": "text-basic",
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

    def _convert_to_elements(self, data: Any, parent: str = "0") -> List[Dict[str, Any]]:
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

    def _convert_element(self, element: Dict[str, Any], parent: str = "0") -> List[Dict[str, Any]]:
        """Convert a single element and its children.

        Bricks Builder 2.x stores pages as a flat array; hierarchy is expressed via
        each element's string ``parent`` id and ``children`` arrays of string ids
        (not nested element objects). Each child recursion's first element is the
        direct child of the parent; its id is pushed into ``parent_el["children"]``.
        """
        el_type = element.get("elType", element.get("type", ""))
        widget_type = element.get("widgetType", "")
        children = element.get("elements", [])

        elements: List[Dict[str, Any]] = []

        if el_type == "section" or el_type == "container":
            parent_el = self._create_section(element, parent)
        elif el_type == "column":
            parent_el = self._create_container(element, parent)
        elif el_type == "widget" or widget_type:
            parent_el = self._create_widget(element, parent)
            self._apply_responsive(element, parent_el)
            elements.append(parent_el)
            return elements
        else:
            parent_el = self._create_generic(element, parent)

        self._apply_responsive(element, parent_el)
        elements.append(parent_el)

        for child in children:
            child_elements = self._convert_element(child, parent_el["id"])
            if child_elements:
                # The first element in the returned list corresponds to the child input,
                # i.e. the direct child of parent_el. Deeper descendants are linked via
                # their own parents in the flat array.
                parent_el["children"].append(child_elements[0]["id"])
                elements.extend(child_elements)

        return elements

    def _apply_responsive(self, element: Dict[str, Any], bricks_element: Dict[str, Any]) -> None:
        """Emit canonical responsive styles as Bricks `:breakpoint` settings."""
        responsive = element_responsive(element) or {}
        canonical = responsive.get("styles")
        if isinstance(canonical, dict):
            suffixed = canonical_to_bricks_settings(canonical)
            if suffixed:
                bricks_element.setdefault("settings", {}).update(suffixed)

    def _create_section(self, element: Dict[str, Any], parent: str) -> Dict[str, Any]:
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

    def _create_container(self, element: Dict[str, Any], parent: str) -> Dict[str, Any]:
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

    def _create_widget(self, element: Dict[str, Any], parent: str) -> Dict[str, Any]:
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

    def _create_generic(self, element: Dict[str, Any], parent: str) -> Dict[str, Any]:
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

        elif widget_type == "testimonial":
            bricks_settings["items"] = [
                {
                    "content": settings.get("testimonial_content", ""),
                    "name": settings.get("testimonial_name", ""),
                    "title": settings.get("testimonial_job", ""),
                }
            ]

        elif widget_type == "call-to-action":
            bricks_settings["heading"] = settings.get("title", "")
            bricks_settings["text"] = settings.get("description", "")
            if settings.get("button_text"):
                bricks_settings["buttonText"] = settings["button_text"]

        elif widget_type == "icon-box":
            bricks_settings["heading"] = settings.get("title_text", "")
            bricks_settings["text"] = settings.get("description_text", "")
            if settings.get("button_text"):
                bricks_settings["buttonText"] = settings["button_text"]

        elif widget_type == "price-table":
            bricks_settings["heading"] = settings.get("heading", "")
            bricks_settings["price"] = settings.get("price", "")
            if settings.get("currency_symbol"):
                bricks_settings["currency"] = settings["currency_symbol"]
            if settings.get("period"):
                bricks_settings["period"] = settings["period"]
            bricks_settings["features"] = [
                {"text": item.get("item_text", "")}
                for item in settings.get("features", [])
                if isinstance(item, dict)
            ]
            if settings.get("button_text"):
                bricks_settings["buttonText"] = settings["button_text"]

        elif widget_type == "alert":
            bricks_settings["heading"] = settings.get("alert_title", "")
            bricks_settings["text"] = settings.get("alert_description", "")
            if settings.get("alert_type"):
                bricks_settings["type"] = settings["alert_type"]

        elif widget_type == "blockquote":
            bricks_settings["text"] = settings.get("blockquote_content", "")
            if settings.get("author"):
                bricks_settings["citation"] = settings["author"]

        elif widget_type == "icon-list":
            bricks_settings["items"] = [
                {"text": item.get("text", "")}
                for item in settings.get("icon_list", [])
                if isinstance(item, dict)
            ]

        elif widget_type == "image-gallery":
            bricks_settings["images"] = [
                {"url": img.get("url", ""), "id": img.get("id", "")}
                for img in settings.get("wp_gallery", [])
                if isinstance(img, dict)
            ]

        elif widget_type == "social-icons":
            bricks_settings["items"] = [
                {
                    "service": item.get("social", ""),
                    "link": (item.get("link") or {}).get("url", "")
                    if isinstance(item.get("link"), dict)
                    else "",
                }
                for item in settings.get("social_icon_list", [])
                if isinstance(item, dict)
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
