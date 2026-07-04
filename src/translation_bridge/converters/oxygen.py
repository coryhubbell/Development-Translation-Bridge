"""
Translation Bridge v4 - Oxygen Builder (Classic 4.x) JSON Converter.

Emits classic Oxygen's real ``ct_builder_json`` post-meta shape — a nested
tree under a ``root`` node — unified with the PHP converter::

    {
        "id": 0,
        "name": "root",
        "depth": 0,
        "children": [
            {"id": 1, "name": "ct_section",
             "options": {"ct_id": 1, "ct_parent": 0,
                          "selector": "section-1-tb", "original": {...}},
             "children": [...]}
        ]
    }

Element vocabulary uses classic Oxygen's real names (``ct_*`` core elements,
``oxy_*`` composites). Design props emit into ``options.original``; canonical
responsive data (``element["responsive"]``, see
``translation_bridge.responsive``) emits into
``options.media.<breakpoint>.original`` (tablet, phone-portrait).
"""

from typing import Any, Dict, List, Optional
import json
import re

from translation_bridge.responsive import element_responsive


# Upstream framework version this converter is calibrated against.
# Classic Oxygen 4.x schema; Oxygen 6 is handled by Oxygen6Converter.
TARGET_CMS_VERSION: str = "4.8.3"

# Canonical breakpoint -> classic Oxygen `options.media` key.
MEDIA_BREAKPOINTS = {"tablet": "tablet", "phone": "phone-portrait"}

# Setting keys that carry user-visible content (matches the fidelity
# convention in transforms/core.py; the exclusion list wins so keys like
# title_color never count as content).
CONTENT_KEY_PARTS = (
    "text", "title", "content", "description", "heading", "editor",
    "caption", "label", "alt", "html", "name", "job", "address", "url", "date",
)
NON_CONTENT_KEY_PARTS = (
    "color", "size", "typography", "weight", "align", "style", "position",
    "gap", "width", "height", "radius", "spacing", "margin", "padding",
    "font", "shadow", "border", "opacity", "hover",
)


def _is_content_key(key: str) -> bool:
    key_lower = str(key).lower()
    if any(part in key_lower for part in NON_CONTENT_KEY_PARTS):
        return False
    return any(part in key_lower for part in CONTENT_KEY_PARTS)


def _content_strings(settings: Dict[str, Any]) -> List[str]:
    """Collect content-bearing strings from settings (one level into dicts/lists)."""
    out: List[str] = []
    for key, value in settings.items():
        if isinstance(value, str) and value.strip() and _is_content_key(key):
            out.append(value.strip())
        elif isinstance(value, dict):
            for sub_key, sub in value.items():
                if isinstance(sub, str) and sub.strip() and _is_content_key(sub_key):
                    out.append(sub.strip())
        elif isinstance(value, list):
            for item in value:
                if not isinstance(item, dict):
                    continue
                for sub_key, sub in item.items():
                    if isinstance(sub, str) and sub.strip() and _is_content_key(sub_key):
                        out.append(sub.strip())
    return out


# Length props classic Oxygen stores unitless (px implied).
UNITLESS_PX_PROPS = frozenset(
    [
        "font-size", "border-radius", "border-width", "width", "height",
        "max-width", "min-width", "max-height", "min-height", "gap",
        "padding-top", "padding-right", "padding-bottom", "padding-left",
        "margin-top", "margin-right", "margin-bottom", "margin-left",
        "top", "right", "bottom", "left", "letter-spacing",
    ]
)


def _unitless(props: Dict[str, Any]) -> Dict[str, Any]:
    """Strip px suffixes on length props (Oxygen stores them unitless)."""
    out = {}
    for prop, value in props.items():
        if isinstance(value, str) and prop in UNITLESS_PX_PROPS:
            match = re.fullmatch(r"(-?\d+(?:\.\d+)?)px", value)
            if match:
                value = match.group(1)
        out[prop] = value
    return out


class OxygenConverter:
    """Converts parsed content to classic Oxygen Builder JSON."""

    # Universal type -> real classic Oxygen element name (matches the PHP
    # converter's map).
    ELEMENT_TYPE_MAP = {
        "container": "ct_div_block",
        "section": "ct_section",
        "row": "ct_new_columns",
        "column": "ct_column",
        "heading": "ct_headline",
        "text": "ct_text_block",
        "text-editor": "ct_text_block",
        "paragraph": "ct_text_block",
        "image": "ct_image",
        "link": "ct_link",
        "button": "ct_link_button",
        "divider": "ct_separator",
        "spacer": "ct_div_block",
        "icon": "ct_fancy_icon",
        "icon-box": "oxy_icon_box",
        "card": "oxy_icon_box",
        "icon-list": "ct_text_block",
        "video": "ct_video",
        "audio": "ct_code_block",
        "gallery": "oxy_gallery",
        "image-gallery": "oxy_gallery",
        "slider": "ct_slider",
        "slides": "ct_slider",
        "slide": "ct_slide",
        "carousel": "ct_slider",
        "tabs": "oxy_tabs",
        "tab": "oxy_tab",
        "accordion": "oxy_toggle",
        "toggle": "oxy_toggle",
        "testimonial": "oxy_testimonial_box",
        "blockquote": "oxy_testimonial_box",
        "quote": "oxy_testimonial_box",
        "counter": "ct_text_block",
        "progress": "oxy_progress_bar",
        "countdown": "ct_text_block",
        "alert": "ct_text_block",
        "call-to-action": "ct_text_block",
        "cta": "ct_text_block",
        "form": "ct_div_block",
        "nav": "oxy_nav_menu",
        "nav-menu": "oxy_nav_menu",
        "menu": "oxy_nav_menu",
        "html": "ct_code_block",
        "code": "ct_code_block",
        "map": "oxy_map",
        "google_maps": "oxy_map",
        "price-table": "oxy_pricing_box",
        "pricing-table": "oxy_pricing_box",
        "social-icons": "oxy_share_box",
        "posts": "oxy_posts_grid",
    }

    # Container element names that must not carry ct_content.
    CONTAINER_ELEMENTS = frozenset(
        ["ct_section", "ct_div_block", "ct_new_columns", "ct_column", "ct_inner_content", "oxy_superbox"]
    )

    def __init__(self):
        self._id_counter = 0

    def convert(self, data: Any) -> str:
        """Convert universal data to an Oxygen root-tree JSON string."""
        return json.dumps(self.convert_to_dict(data), indent=2)

    def convert_to_dict(self, data: Any) -> Dict[str, Any]:
        """Convert universal data to the Oxygen root-tree structure."""
        self._id_counter = 0
        children = self._convert_to_elements(data, parent_id=0)
        return {"id": 0, "name": "root", "depth": 0, "children": children}

    def _convert_to_elements(self, data: Any, parent_id: int) -> List[Dict[str, Any]]:
        if isinstance(data, dict):
            if "elements" in data and isinstance(data["elements"], list):
                return [
                    el
                    for item in data["elements"]
                    if isinstance(item, dict) and (el := self._convert_element(item, parent_id))
                ]
            element = self._convert_element(data, parent_id)
            return [element] if element else []

        if isinstance(data, list):
            return [
                el
                for item in data
                if isinstance(item, dict) and (el := self._convert_element(item, parent_id))
            ]

        return []

    def _convert_element(self, element: Dict[str, Any], parent_id: int) -> Optional[Dict[str, Any]]:
        """Convert a single element and its children."""
        el_type = element.get("elType", element.get("type", ""))
        widget_type = element.get("widgetType", "")

        universal = widget_type or el_type
        if el_type == "section":
            universal = "section"
        elif el_type == "column":
            universal = "column"
        elif el_type == "widget" and not widget_type:
            universal = "text"

        element_name = self.ELEMENT_TYPE_MAP.get(universal, "ct_div_block")

        # Top-level generic containers are sections in real Oxygen documents.
        if parent_id == 0 and element_name == "ct_div_block" and universal in ("container", ""):
            element_name = "ct_section"

        element_id = self._generate_id()
        settings = element.get("settings", element.get("attributes", {})) or {}
        content = element.get("content", "")

        widget_options = self._build_widget_options(universal, element_name, settings, content)

        children_src = list(element.get("elements", element.get("children", [])) or [])
        children_src += self._composite_children(universal, settings)
        children = [
            child
            for item in children_src
            if isinstance(item, dict) and (child := self._convert_element(item, element_id))
        ]

        # A content-carrying widget mapped onto a container element (forms,
        # unknown vocabularies) must not lose its text: leaf widgets become a
        # text block; container-shaped ones get a text-block child instead.
        if widget_options.get("ct_content") and element_name in self.CONTAINER_ELEMENTS:
            if children:
                text = widget_options.pop("ct_content")
                widget_options.pop("text", None)
                children.insert(
                    0,
                    self._convert_element(
                        {"elType": "widget", "widgetType": "text-editor", "settings": {"editor": text}},
                        element_id,
                    ),
                )
            else:
                element_name = "ct_text_block"

        options: Dict[str, Any] = {
            "ct_id": element_id,
            "ct_parent": parent_id,
            "selector": self._selector(element_name, element_id),
            "nicename": f"{(universal or 'element').capitalize()} (#{element_id})",
        }
        options.update(widget_options)

        original = self._build_original(element, settings)
        if original:
            options["original"] = original

        media = self._build_media(element)
        if media:
            options["media"] = media

        return {
            "id": element_id,
            "name": element_name,
            "options": options,
            "children": children,
        }

    def _selector(self, element_name: str, element_id: int) -> str:
        """Deterministic selector mirroring Oxygen's `{type}-{id}-{post}` shape."""
        base = re.sub(r"^(ct_|oxy_)", "", element_name).replace("_", "-")
        return f"{base}-{element_id}-tb"

    def _build_original(self, element: Dict[str, Any], settings: Dict[str, Any]) -> Dict[str, Any]:
        """Design props -> options.original (full passthrough, unitless)."""
        styles = element.get("styles") if isinstance(element.get("styles"), dict) else {}
        css_like = {
            k: v
            for k, v in settings.items()
            if isinstance(v, (str, int, float)) and "-" in str(k)
        }
        merged = {**css_like, **styles}
        return _unitless({k: str(v) for k, v in merged.items() if str(v) != ""})

    def _build_media(self, element: Dict[str, Any]) -> Dict[str, Any]:
        """Canonical responsive styles -> options.media.<breakpoint>.original."""
        responsive = element_responsive(element) or {}
        canonical = responsive.get("styles")
        if not isinstance(canonical, dict):
            return {}

        media: Dict[str, Any] = {}
        for breakpoint, oxygen_key in MEDIA_BREAKPOINTS.items():
            props = canonical.get(breakpoint, {}).get("default")
            if isinstance(props, dict) and props:
                media[oxygen_key] = {"original": _unitless({k: str(v) for k, v in props.items()})}
        return media

    def _build_widget_options(
        self, universal: str, element_name: str, settings: Dict[str, Any], content: str = ""
    ) -> Dict[str, Any]:
        """Build content/semantic Oxygen options from universal settings."""
        options: Dict[str, Any] = {}

        if universal == "heading":
            text = content or settings.get("title", "")
            options["headline_text"] = text
            options["ct_content"] = text
            options["tag"] = settings.get("header_size", settings.get("tag", "h2"))

        elif universal in ("text", "paragraph", "text-editor", "counter"):
            text = content or settings.get("editor", settings.get("text", settings.get("title", "")))
            if text:
                options["text"] = text
                options["ct_content"] = text

        elif universal == "image":
            image = settings.get("image", {})
            if isinstance(image, dict):
                options["src"] = image.get("url", settings.get("src", ""))
                options["alt"] = image.get("alt", settings.get("alt", ""))
            else:
                options["src"] = settings.get("src", settings.get("image_url", ""))
                options["alt"] = settings.get("alt", settings.get("alt_text", ""))

        elif universal in ("button", "link"):
            text = content or settings.get("text", settings.get("label", ""))
            if text:
                options["text"] = text
                options["ct_content"] = text
            link = settings.get("link", {})
            if isinstance(link, dict) and link.get("url"):
                options["url"] = link["url"]
            elif settings.get("url"):
                options["url"] = settings["url"]

        elif universal == "video":
            url = settings.get("youtube_url", settings.get("video_url", settings.get("url", "")))
            if url:
                options["embed_code"] = url
                options["video_type"] = "youtube"

        elif universal == "icon":
            icon = settings.get("selected_icon", settings.get("icon", ""))
            if isinstance(icon, dict):
                icon = icon.get("value", "")
            if icon:
                options["icon"] = icon

        elif universal in ("icon-box", "card"):
            options["heading"] = content or settings.get("title_text", settings.get("title", ""))
            options["text"] = settings.get("description_text", settings.get("description", ""))
            icon = settings.get("selected_icon", {})
            if isinstance(icon, dict) and icon.get("value"):
                options["icon"] = icon["value"]
            if settings.get("button_text"):
                options["button_text"] = settings["button_text"]
            link = settings.get("link", {})
            if isinstance(link, dict) and link.get("url"):
                options["url"] = link["url"]
            image = settings.get("image", {})
            if isinstance(image, dict) and image.get("url"):
                options["image_src"] = image["url"]
                if image.get("alt"):
                    options["image_alt"] = image["alt"]

        elif universal in ("testimonial", "blockquote", "quote"):
            options["testimonial_text"] = content or settings.get(
                "testimonial_content",
                settings.get("blockquote_content", settings.get("quote", "")),
            )
            options["author"] = settings.get("testimonial_name", settings.get("author", ""))
            options["title"] = settings.get("testimonial_job", settings.get("author_title", ""))

        elif universal == "alert":
            parts = []
            if settings.get("alert_title"):
                parts.append(f"<strong>{settings['alert_title']}</strong>")
            body = content or settings.get("alert_description", "")
            if body:
                parts.append(f"<p>{body}</p>")
            if parts:
                options["ct_content"] = "".join(parts)

        elif universal in ("call-to-action", "cta"):
            parts = []
            if settings.get("title"):
                parts.append(f"<h2>{settings['title']}</h2>")
            body = content or settings.get("description", "")
            if body:
                parts.append(f"<p>{body}</p>")
            link = settings.get("link", {})
            url = link.get("url", "") if isinstance(link, dict) else settings.get("url", "")
            button_text = settings.get("button_text", "")
            if button_text or url:
                parts.append(f'<a href="{url}">{button_text}</a>')
            if parts:
                options["ct_content"] = "".join(parts)

        elif universal in ("price-table", "pricing-table"):
            options["heading"] = settings.get("heading", settings.get("title", ""))
            price = settings.get("price", "")
            if price != "":
                options["price"] = f"{settings.get('currency_symbol', '')}{price}"
            if settings.get("period"):
                options["period"] = settings["period"]
            features = settings.get("features", settings.get("items", []))
            texts = [
                item.get("item_text", item.get("text", ""))
                for item in features
                if isinstance(item, dict)
            ]
            if any(texts):
                options["features"] = texts
            if settings.get("button_text"):
                options["button_text"] = settings["button_text"]
            link = settings.get("link", {})
            button_url = settings.get("button_url", "") or (
                link.get("url", "") if isinstance(link, dict) else ""
            )
            if button_url:
                options["button_url"] = button_url

        elif universal == "icon-list":
            items = settings.get("icon_list", settings.get("items", []))
            lis = "".join(
                f"<li>{item['text']}</li>"
                for item in items
                if isinstance(item, dict) and item.get("text")
            )
            if lis:
                options["ct_content"] = f"<ul>{lis}</ul>"

        elif universal in ("tabs", "accordion", "toggle"):
            pass  # tab items expand into child elements (_composite_children)

        elif universal == "audio":
            link = settings.get("link", {})
            url = link.get("url", "") if isinstance(link, dict) else (link or settings.get("url", ""))
            if url:
                options["code"] = f'<audio controls src="{url}"></audio>'

        elif universal in ("map", "google_maps"):
            if settings.get("address"):
                options["address"] = settings["address"]

        elif universal == "progress":
            percent = settings.get("percent", {})
            options["percentage"] = percent.get("size", 50) if isinstance(percent, dict) else percent or 50
            if settings.get("title"):
                options["title"] = settings["title"]

        elif universal in ("html", "code"):
            options["code"] = content or settings.get("html", settings.get("code", ""))

        elif universal in ("gallery", "image-gallery", "carousel", "slider"):
            gallery = settings.get("gallery", settings.get("wp_gallery", []))
            images = [
                {"url": img.get("url", ""), "alt": img.get("alt", ""), "id": img.get("id", "")}
                for img in gallery
                if isinstance(img, dict)
            ]
            if images:
                options["images"] = images
            if settings.get("title"):
                options["title"] = settings["title"]

        elif universal not in ("section", "container", "column", "row"):
            # No native classic-Oxygen equivalent: preserve every
            # content-bearing setting rather than dropping it.
            strings = _content_strings(settings)
            if content and content.strip() and content.strip() not in strings:
                strings.insert(0, content.strip())
            if strings:
                text = "\n".join(strings)
                options["text"] = text
                options["ct_content"] = text

        # Common options.
        if settings.get("align"):
            options["text-align"] = settings["align"]

        return options

    def _composite_children(self, universal: str, settings: Dict[str, Any]) -> List[Dict[str, Any]]:
        """Expand item-list composites (tabs/accordion) into pseudo-universal
        children so each pane survives as real classic-Oxygen elements."""
        if universal not in ("tabs", "accordion", "toggle"):
            return []
        children: List[Dict[str, Any]] = []
        items = settings.get("tabs", settings.get("items", []))
        for item in items if isinstance(items, list) else []:
            if not isinstance(item, dict):
                continue
            if item.get("tab_title"):
                children.append(
                    {
                        "elType": "widget",
                        "widgetType": "heading",
                        "settings": {"title": item["tab_title"], "header_size": "h4"},
                    }
                )
            if item.get("tab_content"):
                children.append(
                    {
                        "elType": "widget",
                        "widgetType": "text-editor",
                        "settings": {"editor": item["tab_content"]},
                    }
                )
        return children

    def _generate_id(self) -> int:
        """Generate unique Oxygen element ID."""
        self._id_counter += 1
        return self._id_counter

    def get_framework(self) -> str:
        return "oxygen"

    def get_supported_types(self) -> List[str]:
        return list(self.ELEMENT_TYPE_MAP.keys())
