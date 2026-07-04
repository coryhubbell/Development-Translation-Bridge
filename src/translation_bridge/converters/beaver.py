"""
Translation Bridge v4 - Beaver Builder JSON Converter.

Converts universal/parsed data TO Beaver Builder JSON format.
Generates proper Beaver Builder node structure.
"""

from typing import Any, Dict, List, Optional
import secrets
import json


# Upstream framework version this converter is calibrated against.
TARGET_CMS_VERSION: str = "2.10.2"


class BeaverConverter:
    """
    Converts parsed content to Beaver Builder JSON format.

    Beaver Builder structure:
    {
        "node_id": {
            "node": "node_id",
            "type": "row|column|module",
            "parent": "parent_id",
            "position": 0,
            "settings": {}
        }
    }
    """

    MODULE_TYPE_MAP = {
        "heading": "heading",
        "text": "rich-text",
        "paragraph": "rich-text",
        "image": "photo",
        "button": "button",
        "divider": "separator",
        "spacer": "separator",
        "icon": "icon",
        "icon-box": "icon-group",
        "video": "video",
        "gallery": "gallery",
        "carousel": "slideshow",
        "tabs": "tabs",
        "accordion": "accordion",
        "testimonial": "testimonials",
        "counter": "numbers",
        "progress": "numbers",
        "form": "contact-form",
        "nav": "menu",
        "menu": "menu",
        "html": "html",
        "cta": "cta",
        "call-to-action": "cta",
        "price-table": "pricing-table",
        "alert": "rich-text",
        "icon-list": "rich-text",
        "blockquote": "rich-text",
        "image-gallery": "gallery",
        "audio": "audio",
        "countdown": "countdown",
        "google_maps": "map",
        "slides": "slideshow",
        "social-icons": "social-buttons",
        "text-editor": "rich-text",
    }

    # Content-bearing setting keys funneled into the fallback so unmapped
    # widgets never emit an empty module (mirrors cli.collect_content_strings).
    _FALLBACK_CONTENT_PARTS = (
        "text", "title", "content", "description", "heading", "editor",
        "caption", "label", "alt", "html", "name", "job", "address", "url", "date",
    )
    _FALLBACK_SKIP_PARTS = (
        "color", "size", "typography", "weight", "align", "style", "position",
        "gap", "width", "height", "radius", "spacing", "margin", "padding",
        "font", "shadow", "border", "opacity", "hover",
    )

    def __init__(self):
        self._position_counter = 0

    def convert(self, data: Any) -> str:
        """Convert universal data to Beaver Builder JSON string."""
        nodes = self._convert_to_nodes(data)
        return json.dumps(nodes, indent=2)

    def convert_to_dict(self, data: Any) -> Dict[str, Any]:
        """Convert universal data to Beaver Builder node dict."""
        return self._convert_to_nodes(data)

    def _convert_to_nodes(self, data: Any, parent: str = "") -> Dict[str, Any]:
        """Convert data to Beaver Builder node structure."""
        nodes = {}
        self._position_counter = 0

        if isinstance(data, dict):
            if "elements" in data:
                for element in data["elements"]:
                    nodes.update(self._convert_element(element, parent))
            else:
                # Single element - wrap in row
                row_id = self._generate_id()
                nodes[row_id] = self._create_row(row_id, parent)
                nodes.update(self._convert_element(data, row_id))

        elif isinstance(data, list):
            for element in data:
                nodes.update(self._convert_element(element, parent))

        return nodes

    def _convert_element(self, element: Dict[str, Any], parent: str) -> Dict[str, Any]:
        """Convert a single element and its children."""
        nodes = {}
        el_type = element.get("elType", element.get("type", ""))
        widget_type = element.get("widgetType", "")
        settings = element.get("settings", element.get("attributes", {}))
        children = element.get("elements", [])

        if el_type == "section" or el_type == "container":
            # Create row
            row_id = self._generate_id()
            nodes[row_id] = self._create_row(row_id, parent, settings)

            # Convert children (columns)
            for child in children:
                nodes.update(self._convert_element(child, row_id))

        elif el_type == "column":
            # Create column group and column
            col_group_id = self._generate_id()
            col_id = self._generate_id()

            nodes[col_group_id] = self._create_column_group(col_group_id, parent)
            nodes[col_id] = self._create_column(col_id, col_group_id, settings)

            # Convert children (modules)
            for child in children:
                nodes.update(self._convert_element(child, col_id))

        elif el_type == "widget" or widget_type:
            # Create module; some sources (e.g. DIVI social follows) nest
            # content widgets inside other widgets, so recurse into children.
            module_id = self._generate_id()
            nodes[module_id] = self._create_module(module_id, parent, widget_type, settings)

            for child in children:
                nodes.update(self._convert_element(child, module_id))

        else:
            # Generic module
            module_id = self._generate_id()
            nodes[module_id] = self._create_module(
                module_id, parent,
                element.get("type", "text"),
                settings
            )

        return nodes

    def _create_row(self, node_id: str, parent: str, settings: Dict[str, Any] = None) -> Dict[str, Any]:
        """Create a Beaver Builder row node."""
        settings = settings or {}
        self._position_counter += 1

        bb_settings = {
            "bg_type": "none",
            "full_height": "default",
            "content_width": "fixed",
        }

        # Background color
        bg_color = settings.get("background_color", "")
        if bg_color and not bg_color.startswith("globals"):
            bb_settings["bg_type"] = "color"
            bb_settings["bg_color"] = bg_color

        return {
            "node": node_id,
            "type": "row",
            "parent": parent or None,
            "position": self._position_counter,
            "settings": bb_settings,
        }

    def _create_column_group(self, node_id: str, parent: str) -> Dict[str, Any]:
        """Create a Beaver Builder column group node."""
        self._position_counter += 1

        return {
            "node": node_id,
            "type": "column-group",
            "parent": parent,
            "position": self._position_counter,
            "settings": {},
        }

    def _create_column(self, node_id: str, parent: str, settings: Dict[str, Any] = None) -> Dict[str, Any]:
        """Create a Beaver Builder column node."""
        settings = settings or {}
        self._position_counter += 1

        col_size = settings.get("_column_size", 100)

        return {
            "node": node_id,
            "type": "column",
            "parent": parent,
            "position": self._position_counter,
            "settings": {
                "size": col_size,
                "size_responsive": "",
            },
        }

    def _create_module(self, node_id: str, parent: str, widget_type: str, settings: Dict[str, Any]) -> Dict[str, Any]:
        """Create a Beaver Builder module node."""
        self._position_counter += 1
        module_type = self.MODULE_TYPE_MAP.get(widget_type, "rich-text")

        bb_settings = self._build_module_settings(widget_type, settings)

        return {
            "node": node_id,
            "type": "module",
            "parent": parent,
            "position": self._position_counter,
            "settings": {
                "type": module_type,
                **bb_settings,
            },
        }

    def _build_module_settings(self, widget_type: str, settings: Dict[str, Any]) -> Dict[str, Any]:
        """Build Beaver Builder module settings."""
        bb_settings = {}

        if widget_type == "heading":
            bb_settings["heading"] = settings.get("title", "Heading")
            bb_settings["tag"] = settings.get("header_size", "h2")

        elif widget_type in ["text", "text-editor"]:
            bb_settings["text"] = settings.get("editor", settings.get("text", ""))

        elif widget_type == "image":
            image = settings.get("image", {})
            if isinstance(image, dict):
                bb_settings["photo_src"] = image.get("url", "")
                bb_settings["photo_source"] = "url"
                if image.get("alt"):
                    bb_settings["photo_alt"] = image["alt"]

        elif widget_type == "button":
            bb_settings["text"] = settings.get("text", "Click Here")
            link = settings.get("link", {})
            if isinstance(link, dict):
                bb_settings["link"] = link.get("url", "#")

        elif widget_type == "video":
            bb_settings["embed_code"] = settings.get("youtube_url", "")

        elif widget_type == "tabs":
            tabs = settings.get("tabs", [])
            bb_settings["items"] = [
                {"label": tab.get("tab_title", "Tab"), "content": tab.get("tab_content", "")}
                for tab in tabs
            ]

        elif widget_type == "accordion":
            items = settings.get("tabs", [])
            bb_settings["items"] = [
                {"label": item.get("tab_title", "Item"), "content": item.get("tab_content", "")}
                for item in items
            ]

        elif widget_type == "testimonial":
            bb_settings["testimonial"] = settings.get("testimonial_content", "")
            bb_settings["name"] = settings.get("testimonial_name", "")
            bb_settings["job"] = settings.get("testimonial_job", "")

        elif widget_type == "counter":
            bb_settings["number"] = settings.get("ending_number", "100")
            bb_settings["before"] = settings.get("prefix", "")
            bb_settings["after"] = settings.get("suffix", "")
            if settings.get("title"):
                bb_settings["text"] = settings["title"]

        elif widget_type == "html":
            bb_settings["html"] = settings.get("html", "")

        elif widget_type == "icon-box":
            bb_settings["title"] = settings.get("title_text", "")
            bb_settings["text"] = settings.get("description_text", "")
            if settings.get("button_text"):
                bb_settings["btn_text"] = settings["button_text"]
            link = settings.get("link", {})
            if isinstance(link, dict) and link.get("url"):
                bb_settings["link"] = link["url"]
            image = settings.get("image", {})
            if isinstance(image, dict) and image.get("url"):
                bb_settings["photo_src"] = image["url"]
                if image.get("alt"):
                    bb_settings["photo_alt"] = image["alt"]

        elif widget_type in ["call-to-action", "cta"]:
            bb_settings["title"] = settings.get("title", "")
            bb_settings["text"] = settings.get("description", "")
            if settings.get("button_text"):
                bb_settings["btn_text"] = settings["button_text"]
            link = settings.get("link", {})
            if isinstance(link, dict) and link.get("url"):
                bb_settings["btn_link"] = link["url"]

        elif widget_type == "price-table":
            column = {
                "title": settings.get("heading", ""),
                "price": settings.get("price", ""),
                "duration": settings.get("period", ""),
                "features": [
                    item.get("item_text", "")
                    for item in settings.get("features", [])
                    if isinstance(item, dict)
                ],
                "btn_text": settings.get("button_text", ""),
                "btn_link": settings.get("button_url", ""),
            }
            if settings.get("currency_symbol"):
                column["currency"] = settings["currency_symbol"]
            bb_settings["pricing_columns"] = [column]

        elif widget_type == "alert":
            title = settings.get("alert_title", "")
            description = settings.get("alert_description", "")
            bb_settings["text"] = f"<strong>{title}</strong> {description}".strip()

        elif widget_type == "icon-list":
            items = "".join(
                f"<li>{item.get('text', '')}</li>"
                for item in settings.get("icon_list", [])
                if isinstance(item, dict)
            )
            bb_settings["text"] = f"<ul>{items}</ul>" if items else ""

        elif widget_type == "blockquote":
            content = settings.get("blockquote_content", "")
            author = settings.get("author", "")
            cite = f"<cite>{author}</cite>" if author else ""
            bb_settings["text"] = f"<blockquote>{content}{cite}</blockquote>"

        elif widget_type == "image-gallery":
            bb_settings["photos"] = [
                {"url": img.get("url", ""), "alt": img.get("alt", ""), "id": img.get("id", "")}
                for img in settings.get("wp_gallery", [])
                if isinstance(img, dict)
            ]

        elif widget_type == "audio":
            link = settings.get("link", {})
            bb_settings["audio"] = link.get("url", "") if isinstance(link, dict) else ""

        elif widget_type == "countdown":
            if settings.get("title"):
                bb_settings["text"] = settings["title"]
            if settings.get("due_date"):
                bb_settings["date"] = settings["due_date"]

        elif widget_type == "google_maps":
            bb_settings["address"] = settings.get("address", "")

        elif widget_type == "progress":
            percent = settings.get("percent", {})
            bb_settings["number"] = percent.get("size", 0) if isinstance(percent, dict) else percent
            if settings.get("title"):
                bb_settings["text"] = settings["title"]

        elif widget_type == "form":
            if settings.get("title"):
                bb_settings["title"] = settings["title"]
            if settings.get("form_name"):
                bb_settings["form_name"] = settings["form_name"]
            fields = settings.get("form_fields", [])
            if fields:
                bb_settings["fields"] = [
                    {"type": field.get("field_type", "text"), "label": field.get("field_label", "")}
                    for field in fields
                    if isinstance(field, dict)
                ]

        else:
            # Content-preserving fallback: unmapped widgets become rich-text
            # carrying every content-bearing setting instead of an empty module.
            fallback = self._fallback_content(settings)
            if fallback:
                bb_settings["text"] = fallback

        return bb_settings

    def _fallback_content(self, settings: Dict[str, Any]) -> str:
        """Join content-bearing settings values (incl. nested dict/list items)."""
        parts: List[str] = []

        def is_content(key: str) -> bool:
            key_lower = key.lower()
            if any(part in key_lower for part in self._FALLBACK_SKIP_PARTS):
                return False
            return any(part in key_lower for part in self._FALLBACK_CONTENT_PARTS)

        def add(key: str, value: Any) -> None:
            if isinstance(value, str) and value.strip() and is_content(key):
                parts.append(value.strip())

        for key, value in settings.items():
            add(key, value)
            if isinstance(value, dict):
                for sub_key, sub in value.items():
                    add(sub_key, sub)
            elif isinstance(value, list):
                for item in value:
                    if isinstance(item, dict):
                        for sub_key, sub in item.items():
                            add(sub_key, sub)
        return "\n".join(parts)

    def _generate_id(self) -> str:
        """Generate unique Beaver Builder node ID."""
        return secrets.token_hex(6)

    def get_framework(self) -> str:
        return "beaver-builder"

    def get_supported_types(self) -> List[str]:
        return list(self.MODULE_TYPE_MAP.keys())
