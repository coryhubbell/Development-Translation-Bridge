"""
Translation Bridge v4 - Universal interchange (RFC 5.0, Phase 2).

Python mirror of the PHP component→universal bridge
(DEVTB_Component::to_universal() + DEVTB_Universal::components_to_document()).
Converts legacy DEVTB_Component-shaped dicts (type/attributes/content/children,
the pre-5.0 PHP interchange) into canonical universal element dicts
(elType/widgetType/settings/elements) specified in
schema/universal-element.schema.json.

Both engines MUST translate identically: the dual-engine conformance suite
asserts component_to_element(component.to_array()) == component.to_universal()
on real parsed fixtures. Any vocabulary change here needs the matching change
in translation-bridge/models/class-component.php (and vice versa).

The component shape is deprecated interchange — Phase 4 removes it from the
public surface. New callers should pass universal documents directly.
"""

import json
import re
from typing import Any, Dict, List, Optional


# Universal component type → canonical widgetType.
# Mirror of DEVTB_Component::UNIVERSAL_WIDGET_TYPES.
UNIVERSAL_WIDGET_TYPES: Dict[str, str] = {
    "text": "text-editor",
    "paragraph": "text-editor",
    "heading": "heading",
    "image": "image",
    "button": "button",
    "link": "button",
    "list": "icon-list",
    "gallery": "image-gallery",
    "video": "video",
    "audio": "audio",
    "divider": "divider",
    "spacer": "spacer",
    "blockquote": "testimonial",
    "quote": "testimonial",
    "testimonial": "testimonial",
    "html": "html",
    "code": "html",
    "icon": "icon",
    "card": "icon-box",
    "cta": "call-to-action",
    "counter": "counter",
    "pricing-table": "price-table",
    "alert": "alert",
    "tabs": "tabs",
    "accordion": "accordion",
    "toggle": "accordion",
    "slider": "slides",
    "slide": "slides",
    "social-icons": "social-icons",
    "nav": "nav",
    "menu": "nav",
    "form": "form",
    "countdown": "countdown",
    "map": "google_maps",
    "progress": "progress",
}

# Structural component type → elType. Mirror of the map in to_universal().
_STRUCTURAL_EL_TYPES: Dict[str, str] = {
    "container": "section",
    "section": "section",
    "row": "container",
    "column": "column",
}


def components_to_document(components: List[Dict[str, Any]]) -> Dict[str, Any]:
    """Wrap component dicts in a canonical universal document.

    Mirror of DEVTB_Universal::components_to_document().
    """
    return {
        "elements": [
            component_to_element(component)
            for component in components
            if isinstance(component, dict)
        ],
        "version": "",
        "title": "",
        "meta": {"interchange": "universal"},
    }


def component_to_element(component: Dict[str, Any], is_inner: bool = False) -> Dict[str, Any]:
    """Convert one component dict to a universal element (recursive).

    Mirror of DEVTB_Component::to_universal().
    """
    comp_type = _to_str(component.get("type"))

    if comp_type in _STRUCTURAL_EL_TYPES:
        el_type = _STRUCTURAL_EL_TYPES[comp_type]
        if is_inner and el_type == "section":
            el_type = "container"
        element: Dict[str, Any] = {
            "id": _to_str(component.get("id")),
            "elType": el_type,
            "settings": {},
            "elements": [],
        }
    else:
        widget_type = UNIVERSAL_WIDGET_TYPES.get(comp_type, "html")
        attributes = component.get("attributes")
        element = {
            "id": _to_str(component.get("id")),
            "elType": "widget",
            "widgetType": widget_type,
            "settings": _universal_settings(
                widget_type,
                attributes if isinstance(attributes, dict) else {},
                _to_str(component.get("content")).strip(),
            ),
            "elements": [],
        }

    if is_inner:
        element["isInner"] = True

    metadata = component.get("metadata")
    responsive = metadata.get("responsive") if isinstance(metadata, dict) else None
    if isinstance(responsive, dict) and responsive:
        element["responsive"] = responsive

    for child in component.get("children") or []:
        if isinstance(child, dict):
            element["elements"].append(component_to_element(child, is_inner=True))

    return element


def _universal_settings(
    widget_type: str, attrs: Dict[str, Any], content: str
) -> Dict[str, Any]:
    """Build Elementor-style settings from universal attributes + content.

    Mirror of DEVTB_Component::universal_settings().
    """
    out: Dict[str, Any] = {}

    if widget_type == "heading":
        out["title"] = content if content else _first_str(attrs, "heading", "title")
        level = attrs.get("level", 2)
        if isinstance(level, str) and level.startswith("h"):
            out["header_size"] = level
        else:
            out["header_size"] = f"h{_php_int(level)}"
    elif widget_type == "text-editor":
        out["editor"] = content if content else _first_str(attrs, "text")
    elif widget_type == "button":
        out["text"] = content if content else _first_str(attrs, "label", "text")
        url = _first_str(attrs, "url", "href")
        if url:
            out["link"] = {"url": url}
            if _to_str(attrs.get("target")) == "_blank":
                out["link"]["is_external"] = "on"
    elif widget_type == "image":
        out["image"] = {"url": _first_str(attrs, "image_url", "src")}
        alt = _first_str(attrs, "alt_text", "alt")
        if alt:
            out["image"]["alt"] = alt
    elif widget_type == "testimonial":
        out["testimonial_content"] = (
            content if content else _first_str(attrs, "testimonial_content")
        )
        name = _first_str(attrs, "author", "testimonial_name", "cite")
        if name:
            out["testimonial_name"] = name
        job = _first_str(attrs, "job_title", "testimonial_job")
        if job:
            out["testimonial_job"] = job
    elif widget_type == "icon-box":
        out["title_text"] = _first_str(attrs, "heading", "title", "title_text")
        out["description_text"] = (
            content if content else _first_str(attrs, "description")
        )
    elif widget_type == "call-to-action":
        out["title"] = _first_str(attrs, "heading", "title")
        out["description"] = content
        if _filled(attrs.get("label")):
            out["button_text"] = _to_str(attrs.get("label"))
        if _filled(attrs.get("url")):
            out["link"] = {"url": _to_str(attrs.get("url"))}
    elif widget_type in ("accordion", "tabs"):
        items = _first_value(attrs, "tabs", "items")
        items = _decode_json_list(items)
        if isinstance(items, list):
            out["tabs"] = items
        elif _filled(attrs.get("heading")) or content:
            out["tabs"] = [
                {"tab_title": _to_str(attrs.get("heading")), "tab_content": content}
            ]
    elif widget_type == "counter":
        title = _first_value(attrs, "heading", "title")
        out["title"] = _to_str(title) if title is not None else content
        if _filled(attrs.get("number")):
            out["ending_number"] = _to_str(attrs.get("number"))
    elif widget_type == "html":
        out["html"] = content if content else _first_str(attrs, "html", "code")
    elif widget_type == "video":
        out["youtube_url"] = _first_str(attrs, "url", "src", "image_url")
    elif widget_type == "alert":
        out["alert_title"] = _first_str(attrs, "heading", "title")
        out["alert_description"] = (
            content if content else _first_str(attrs, "description")
        )
    elif widget_type == "icon":
        icon = _first_str(attrs, "icon")
        if icon:
            out["selected_icon"] = {"value": icon}
    elif widget_type == "image-gallery":
        images = _decode_json_list(_first_value(attrs, "images", "wp_gallery"))
        if isinstance(images, list):
            out["wp_gallery"] = images
    elif widget_type == "icon-list":
        items = _decode_json_list(
            _first_value(attrs, "items", "icon_list", "list_items")
        )
        if isinstance(items, list):
            out["icon_list"] = items
    else:
        if content:
            out["text"] = content
        if _filled(attrs.get("heading")):
            out["title"] = _to_str(attrs.get("heading"))

    return out


# ----- Reverse direction: universal element → component dict -----
# Mirror of DEVTB_Universal::element_to_component / settings_to_attributes.

# Canonical widgetType → universal component type (reverse of
# UNIVERSAL_WIDGET_TYPES primaries). Mirror of DEVTB_Universal::COMPONENT_TYPES.
COMPONENT_TYPES: Dict[str, str] = {
    "text-editor": "text",
    "heading": "heading",
    "image": "image",
    "button": "button",
    "icon-list": "list",
    "image-gallery": "gallery",
    "video": "video",
    "audio": "audio",
    "divider": "divider",
    "spacer": "spacer",
    "testimonial": "testimonial",
    "html": "html",
    "icon": "icon",
    "icon-box": "card",
    "call-to-action": "cta",
    "counter": "counter",
    "price-table": "pricing-table",
    "alert": "alert",
    "tabs": "tabs",
    "accordion": "accordion",
    "slides": "slider",
    "social-icons": "social-icons",
    "nav": "nav",
    "form": "form",
    "countdown": "countdown",
    "google_maps": "map",
    "progress": "progress",
}

_STRUCTURAL_COMPONENT_TYPES: Dict[str, str] = {
    "section": "container",
    "container": "row",
    "column": "column",
}


def document_to_components(document: Any) -> List[Dict[str, Any]]:
    """Build component dicts from a universal document (or element list).

    Mirror of DEVTB_Universal::document_to_components().
    """
    if isinstance(document, dict) and "elType" in document:
        elements = [document]
    elif isinstance(document, dict) and isinstance(document.get("elements"), list):
        elements = document["elements"]
    elif isinstance(document, list):
        elements = document
    else:
        return []

    components = []
    for element in elements:
        if isinstance(element, dict):
            component = element_to_component(element)
            if component is not None:
                components.append(component)
    return components


def element_to_component(element: Dict[str, Any]) -> Optional[Dict[str, Any]]:
    """Convert a single universal element to a component dict (recursive).

    Mirror of DEVTB_Universal::element_to_component().
    """
    el_type = _to_str(element.get("elType"))
    settings = element.get("settings")
    settings = settings if isinstance(settings, dict) else {}

    if el_type in _STRUCTURAL_COMPONENT_TYPES:
        comp_type = _STRUCTURAL_COMPONENT_TYPES[el_type]
        attributes: Dict[str, Any] = {}
        content = ""
    elif el_type == "widget":
        widget_type = _to_str(element.get("widgetType"))
        comp_type = COMPONENT_TYPES.get(widget_type, "html")
        attributes, content = _settings_to_attributes(widget_type, settings)
    else:
        return None

    metadata: Dict[str, Any] = {
        "source_framework": "universal",
        "original_type": element.get("widgetType") or el_type,
    }
    responsive = element.get("responsive")
    if isinstance(responsive, dict):
        metadata["responsive"] = responsive

    component: Dict[str, Any] = {
        "type": comp_type,
        "category": "general",
        "attributes": attributes,
        "styles": {},
        "content": content,
        "metadata": metadata,
        "children": [],
    }
    if element.get("id"):
        component["id"] = _to_str(element["id"])

    for child in element.get("elements") or []:
        if isinstance(child, dict):
            child_component = element_to_component(child)
            if child_component is not None:
                component["children"].append(child_component)

    return component


def _settings_to_attributes(widget_type: str, settings: Dict[str, Any]):
    """Reverse the settings vocabulary into universal attributes + content.

    Mirror of DEVTB_Universal::settings_to_attributes().
    """
    attrs: Dict[str, Any] = {}
    content = ""

    if widget_type == "heading":
        content = _to_str(settings.get("title", ""))
        size = _to_str(settings.get("header_size") or "h2")
        attrs["level"] = _php_int(size.lstrip("h")) or 2
    elif widget_type == "text-editor":
        content = _first_str(settings, "editor", "text")
    elif widget_type == "button":
        content = _to_str(settings.get("text", ""))
        link = settings.get("link")
        if isinstance(link, dict) and _filled(link.get("url")):
            attrs["url"] = _to_str(link["url"])
            if _filled(link.get("is_external")):
                attrs["target"] = "_blank"
    elif widget_type == "image":
        image = settings.get("image")
        if isinstance(image, dict):
            attrs["image_url"] = _to_str(image.get("url", ""))
            if _filled(image.get("alt")):
                attrs["alt_text"] = _to_str(image["alt"])
    elif widget_type == "testimonial":
        content = _to_str(settings.get("testimonial_content", ""))
        if _filled(settings.get("testimonial_name")):
            attrs["author"] = _to_str(settings["testimonial_name"])
        if _filled(settings.get("testimonial_job")):
            attrs["job_title"] = _to_str(settings["testimonial_job"])
    elif widget_type == "icon-box":
        attrs["heading"] = _to_str(settings.get("title_text", ""))
        content = _to_str(settings.get("description_text", ""))
    elif widget_type == "call-to-action":
        attrs["heading"] = _to_str(settings.get("title", ""))
        content = _to_str(settings.get("description", ""))
        if _filled(settings.get("button_text")):
            attrs["label"] = _to_str(settings["button_text"])
        link = settings.get("link")
        if isinstance(link, dict) and _filled(link.get("url")):
            attrs["url"] = _to_str(link["url"])
    elif widget_type in ("tabs", "accordion"):
        if isinstance(settings.get("tabs"), list):
            attrs["tabs"] = settings["tabs"]
    elif widget_type == "counter":
        attrs["heading"] = _to_str(settings.get("title", ""))
        if _filled(settings.get("ending_number")):
            attrs["number"] = _to_str(settings["ending_number"])
    elif widget_type == "html":
        content = _to_str(settings.get("html", ""))
    elif widget_type == "video":
        attrs["url"] = _to_str(settings.get("youtube_url", ""))
    elif widget_type == "alert":
        attrs["heading"] = _to_str(settings.get("alert_title", ""))
        content = _to_str(settings.get("alert_description", ""))
    elif widget_type == "icon":
        icon = settings.get("selected_icon")
        if isinstance(icon, dict) and _filled(icon.get("value")):
            attrs["icon"] = _to_str(icon["value"])
    elif widget_type == "image-gallery":
        if isinstance(settings.get("wp_gallery"), list):
            attrs["images"] = settings["wp_gallery"]
    elif widget_type == "icon-list":
        if isinstance(settings.get("icon_list"), list):
            attrs["items"] = settings["icon_list"]
    else:
        content = _first_str(settings, "text", "title")

    return attrs, content


# ----- PHP-semantics helpers -----


def _to_str(value: Any) -> str:
    """PHP (string) cast: None → ''."""
    if value is None:
        return ""
    if isinstance(value, bool):
        return "1" if value else ""
    return str(value)


def _first_value(attrs: Dict[str, Any], *keys: str) -> Any:
    """PHP null-coalescing chain: first key present with a non-None value."""
    for key in keys:
        value = attrs.get(key)
        if value is not None:
            return value
    return None


def _first_str(attrs: Dict[str, Any], *keys: str) -> str:
    value = _first_value(attrs, *keys)
    return _to_str(value)


def _filled(value: Any) -> bool:
    """PHP !empty(): rejects None, False, '', 0, '0', and empty collections."""
    if value in (None, False, "", 0, "0"):
        return False
    if isinstance(value, (list, dict)) and not value:
        return False
    return True


def _php_int(value: Any) -> int:
    """PHP (int) cast: leading-numeric-prefix parse, 0 on failure."""
    if isinstance(value, bool):
        return int(value)
    if isinstance(value, (int, float)):
        return int(value)
    match = re.match(r"\s*(-?\d+)", _to_str(value))
    return int(match.group(1)) if match else 0


def _decode_json_list(value: Any) -> Optional[Any]:
    """Mirror the tabs-style pattern: JSON-decode strings, pass lists through."""
    if isinstance(value, str):
        try:
            decoded = json.loads(value)
        except (ValueError, TypeError):
            return None
        return decoded if isinstance(decoded, list) else None
    return value
