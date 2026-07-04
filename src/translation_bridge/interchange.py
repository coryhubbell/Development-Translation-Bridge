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
