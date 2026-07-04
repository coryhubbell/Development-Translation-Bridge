"""
Translation Bridge v4 - Kadence Blocks Converter.

Converts universal/parsed data to Kadence Blocks markup. Kadence Blocks uses
Gutenberg block serialization with the `kadence/` namespace and requires a
`uniqueID` on every block. Theme template parts (Kadence Theme 1.5.x) share
the same namespace and serialize through this same path.

Sibling of `translation-bridge/converters/class-kadence-converter.php`.
"""

from typing import Any, Dict, List, Optional
from html import escape
import json
import hashlib

from ..interchange import document_to_components


# Upstream framework version (Kadence Blocks plugin) this converter is
# calibrated against. Theme template parts (Kadence Theme 1.5.x) share the
# same kadence/* namespace and are emitted through the same code path.
TARGET_CMS_VERSION: str = "3.7.2"


# Settings keys that carry user-visible content (mirror of the CLI fidelity
# probe). Used to rescue strings the reverse interchange has no slot for.
_CONTENT_KEY_PARTS: tuple = (
    "text", "title", "content", "description", "heading", "editor",
    "caption", "label", "alt", "html", "name", "job", "address", "url", "date",
)

# elTypes the reverse interchange converts (everything else it skips).
_UNIVERSAL_EL_TYPES: tuple = ("section", "container", "column", "widget")


def _is_universal_element(value: Any) -> bool:
    return isinstance(value, dict) and "elType" in value


def _element_content_strings(settings: Any) -> List[str]:
    """Content-bearing strings in one element's settings (children excluded)."""
    out: List[str] = []

    def maybe(key: str, value: Any) -> None:
        if isinstance(value, str) and value.strip() and any(
            part in key.lower() for part in _CONTENT_KEY_PARTS
        ):
            out.append(value.strip())

    if not isinstance(settings, dict):
        return out
    for key, value in settings.items():
        if isinstance(value, dict):
            for sub_key, sub in value.items():
                maybe(sub_key, sub)
        elif isinstance(value, list):
            for item in value:
                if isinstance(item, dict):
                    for sub_key, sub in item.items():
                        maybe(sub_key, sub)
        else:
            maybe(key, value)
    return out


def _component_kept_strings(component: Dict[str, Any]) -> str:
    """Every string the component can still express, for extras diffing."""
    parts: List[str] = [str(component.get("content") or "")]

    def collect(value: Any) -> None:
        if isinstance(value, str):
            parts.append(value)
        elif isinstance(value, dict):
            for sub in value.values():
                collect(sub)
        elif isinstance(value, list):
            for sub in value:
                collect(sub)

    collect(component.get("attributes") or {})
    return "\n".join(parts)


def _graft_universal_extras(
    elements: List[Any], components: List[Dict[str, Any]]
) -> None:
    """Attach settings strings the reverse interchange dropped (no attribute
    slot, e.g. price-table rows) so the converted output never loses them."""
    index = 0
    for element in elements:
        if not _is_universal_element(element):
            continue
        if str(element.get("elType") or "") not in _UNIVERSAL_EL_TYPES:
            continue
        if index >= len(components):
            break
        component = components[index]
        index += 1
        kept = _component_kept_strings(component)
        extras = [
            value
            for value in _element_content_strings(element.get("settings"))
            if value not in kept
        ]
        if extras:
            component["_universal_extras"] = extras
        _graft_universal_extras(
            element.get("elements") or [], component.get("children") or []
        )


class KadenceConverter:
    """Convert universal data to Kadence Blocks markup.

    Kadence Blocks layout: kadence/rowlayout > kadence/column > leaf blocks.
    Leaf blocks include kadence/advancedheading, kadence/advancedbtn,
    kadence/icon, kadence/image, kadence/spacer, kadence/infobox,
    kadence/tabs, kadence/accordion, kadence/posts (Pro). Body text falls
    through to core/* so it round-trips cleanly through Gutenberg.
    """

    BLOCK_TYPE_MAP: Dict[str, str] = {
        "heading": "kadence/advancedheading",
        "button": "kadence/advancedbtn",
        "icon": "kadence/icon",
        "image": "kadence/image",
        "spacer": "kadence/spacer",
        "divider": "kadence/spacer",
        "infobox": "kadence/infobox",
        "card": "kadence/infobox",
        "tabs": "kadence/tabs",
        "accordion": "kadence/accordion",
        "posts": "kadence/posts",
        "cta": "kadence/infobox",
        "alert": "kadence/infobox",
        "counter": "kadence/countup",
        # No Kadence-namespace equivalent — fall through to core/*.
        "text": "core/paragraph",
        "paragraph": "core/paragraph",
        "list": "core/list",
        "quote": "core/quote",
        "testimonial": "core/quote",
        "code": "core/code",
        "html": "core/html",
        "video": "core/video",
        "gallery": "core/gallery",
    }

    def __init__(self) -> None:
        self._id_counter = 0

    # --- Public surface -------------------------------------------------

    def convert(self, data: Any) -> str:
        """Convert universal data to a Kadence Blocks markup string."""
        return "".join(
            self._convert_component(c) for c in self._normalize_input(data)
        )

    def get_framework(self) -> str:
        return "kadence"

    def get_supported_types(self) -> List[str]:
        return [
            "row", "container", "column",
            "heading", "button", "icon", "image", "spacer", "divider",
            "infobox", "card", "cta", "alert", "counter",
            "tabs", "accordion", "posts",
            "text", "paragraph", "list", "quote", "testimonial",
            "code", "html", "video", "gallery",
        ]

    def supports_type(self, type_name: str) -> bool:
        return type_name in self.get_supported_types()

    def get_fallback(self, component: Dict[str, Any]) -> str:
        parts: List[str] = []
        content = component.get("content", "") or ""
        if content:
            parts.append(str(content))
        attrs = component.get("attributes", {}) or {}
        for key in ("heading", "title", "label", "number", "url", "author", "job_title"):
            value = attrs.get(key)
            if isinstance(value, str) and value.strip():
                parts.append(value.strip())
        for key in ("tabs", "items", "images"):
            value = attrs.get(key)
            if not isinstance(value, list):
                continue
            for item in value:
                if isinstance(item, dict):
                    parts.extend(
                        str(sub).strip()
                        for sub in item.values()
                        if isinstance(sub, str) and sub.strip()
                    )
        body = "\n".join(parts)
        return f"<!-- wp:html -->\n{body}\n<!-- /wp:html -->\n\n"

    # --- Internal -------------------------------------------------------

    def _normalize_input(self, data: Any) -> List[Dict[str, Any]]:
        """Normalize input to component dicts; universal elements route
        through the reverse interchange (plus dropped-content grafting)."""
        if isinstance(data, list):
            elements = data
        elif _is_universal_element(data):
            elements = [data]
        elif isinstance(data, dict) and isinstance(data.get("elements"), list):
            elements = data["elements"]
        elif isinstance(data, dict):
            return [data]
        else:
            return []

        if any(_is_universal_element(e) for e in elements):
            components = document_to_components(elements)
            _graft_universal_extras(elements, components)
            return components
        return [e for e in elements if isinstance(e, dict)]

    def _convert_component(self, component: Dict[str, Any]) -> str:
        if not isinstance(component, dict):
            return ""

        comp_type = component.get("type", "")

        if comp_type in ("container", "row"):
            markup = self._convert_rowlayout(component)
        elif comp_type == "column":
            markup = self._convert_column(component)
        else:
            markup = self._convert_block(component)
        return markup + self._render_universal_extras(component)

    def _render_universal_extras(self, component: Dict[str, Any]) -> str:
        extras = component.get("_universal_extras") or []
        if not extras:
            return ""
        body = "\n".join(extras)
        return f"<!-- wp:html -->\n{body}\n<!-- /wp:html -->\n\n"

    def _convert_rowlayout(self, component: Dict[str, Any]) -> str:
        children = component.get("children", []) or []
        columns = [c for c in children if isinstance(c, dict) and c.get("type") == "column"]
        col_count = max(1, len(columns))

        attrs = {
            "uniqueID": self._next_unique_id(),
            "columns": col_count,
            "colLayout": "row" if col_count == 1 else "equal",
            **self._denormalize_attributes(component.get("attributes", {})),
        }

        opening = self._delimiter("kadence/rowlayout", attrs)
        body_parts = [
            '<div class="kb-row-layout-wrap alignnone">',
            f'<div class="kt-row-column-wrap kt-has-{col_count}-columns">',
        ]

        for child in (columns if columns else children):
            body_parts.append(self._convert_component(child))

        body_parts.append("</div>")
        body_parts.append("</div>")
        closing = self._closing("kadence/rowlayout")

        return f"{opening}\n" + "\n".join(body_parts) + f"\n{closing}\n\n"

    def _convert_column(self, component: Dict[str, Any]) -> str:
        unique = self._next_unique_id()
        attrs = {"uniqueID": unique, **self._denormalize_attributes(component.get("attributes", {}))}

        opening = self._delimiter("kadence/column", attrs)
        body = [
            f'<div class="kadence-column{escape(unique)} inner-column">',
            '<div class="kt-inside-inner-col">',
        ]

        for child in component.get("children", []) or []:
            body.append(self._convert_component(child))

        body.append("</div>")
        body.append("</div>")
        closing = self._closing("kadence/column")

        return f"{opening}\n" + "\n".join(body) + f"\n{closing}\n"

    def _convert_block(self, component: Dict[str, Any]) -> str:
        comp_type = component.get("type", "")
        block_name = self.BLOCK_TYPE_MAP.get(comp_type)

        if not block_name:
            markup = self.get_fallback(component)
        else:
            attrs = self._denormalize_attributes(component.get("attributes", {}))
            attrs = self._lift_content_fields(block_name, attrs, component)

            # Every kadence/* block requires a uniqueID.
            if block_name.startswith("kadence/"):
                attrs = {"uniqueID": self._next_unique_id(), **attrs}

            inner_html = self._inner_html(block_name, component, attrs)

            opening = self._delimiter(block_name, attrs)
            closing = self._closing(block_name)
            markup = f"{opening}\n{inner_html}\n{closing}\n\n"

        # Leaf components can still carry children (universal sources nest
        # freely) — never drop them.
        for child in component.get("children", []) or []:
            markup += self._convert_component(child)
        return markup

    def _lift_content_fields(
        self, block_name: str, attrs: Dict[str, Any], component: Dict[str, Any]
    ) -> Dict[str, Any]:
        comp_attrs = component.get("attributes", {}) or {}

        if block_name == "kadence/advancedheading":
            attrs["level"] = int(comp_attrs.get("level", 2))
        elif block_name in ("kadence/image", "core/image"):
            url = comp_attrs.get("image_url") or comp_attrs.get("src")
            if url:
                attrs["url"] = url
            alt = comp_attrs.get("alt_text") or comp_attrs.get("alt")
            if alt:
                attrs["alt"] = alt
        elif block_name == "kadence/spacer":
            attrs["spacerHeight"] = int(comp_attrs.get("height", 40))
        elif block_name == "kadence/icon":
            icon_name = comp_attrs.get("icon")
            if icon_name:
                attrs["icons"] = [{"icon": str(icon_name)}]

        return attrs

    def _inner_html(
        self, block_name: str, component: Dict[str, Any], attrs: Dict[str, Any]
    ) -> str:
        content = component.get("content", "") or ""
        comp_attrs = component.get("attributes", {}) or {}
        unique = attrs.get("uniqueID", "")

        if block_name == "kadence/advancedheading":
            level = int(comp_attrs.get("level", 2))
            return (
                f'<h{level} class="kt-adv-heading{escape(unique)} '
                f'wp-block-kadence-advancedheading">{escape(content)}</h{level}>'
            )

        if block_name == "kadence/advancedbtn":
            url = comp_attrs.get("url") or comp_attrs.get("href") or "#"
            return (
                '<div class="wp-block-kadence-advancedbtn kb-btns-outer-wrap '
                'kt-btn-align-inherit">'
                f'<a href="{escape(url)}" class="kt-button kt-btn-{escape(unique)} '
                'kb-btn-global-inherit">'
                f'<span class="kt-btn-text">{escape(content)}</span></a></div>'
            )

        if block_name == "kadence/icon":
            return (
                '<div class="wp-block-kadence-icon kt-svg-icons '
                f'kt-svg-icons-{escape(unique)}"></div>'
            )

        if block_name == "kadence/image":
            url = comp_attrs.get("image_url") or comp_attrs.get("src") or ""
            alt = comp_attrs.get("alt_text") or comp_attrs.get("alt") or ""
            return (
                f'<figure class="wp-block-kadence-image kb-image-{escape(unique)}">'
                f'<img src="{escape(url)}" alt="{escape(alt)}"/></figure>'
            )

        if block_name == "kadence/spacer":
            height = int(comp_attrs.get("height", 40))
            return (
                '<div class="wp-block-kadence-spacer aligncenter '
                f'kt-block-spacer-{escape(unique)}" style="height:{height}px"></div>'
            )

        if block_name == "kadence/infobox":
            title = comp_attrs.get("heading") or comp_attrs.get("title") or ""
            label = comp_attrs.get("label") or ""
            url = comp_attrs.get("url") or comp_attrs.get("href") or ""
            parts = [
                f'<div class="wp-block-kadence-infobox kt-info-box-{escape(unique)}">'
            ]
            if title:
                parts.append(
                    f'<div class="kt-blocks-info-box-title">{escape(str(title))}</div>'
                )
            parts.append(
                f'<div class="kt-blocks-info-box-text">{escape(content)}</div>'
            )
            if label or url:
                parts.append(
                    f'<a class="kt-blocks-info-box-learnmore" href="{escape(str(url) or "#")}">'
                    f"{escape(str(label))}</a>"
                )
            parts.append("</div>")
            return "".join(parts)

        if block_name == "kadence/countup":
            title = comp_attrs.get("heading") or comp_attrs.get("title") or content
            number = comp_attrs.get("number", "")
            return (
                f'<div class="wp-block-kadence-countup kt-countup-{escape(unique)}">'
                f'<div class="kt-countup-number">{escape(str(number))}</div>'
                f'<div class="kt-countup-title">{escape(str(title))}</div></div>'
            )

        if block_name == "kadence/tabs":
            return (
                '<div class="wp-block-kadence-tabs kt-tabs-wrap '
                f'kt-tabs-id-{escape(unique)}">{self._tab_panes(comp_attrs)}</div>'
            )

        if block_name == "kadence/accordion":
            return (
                '<div class="wp-block-kadence-accordion kt-accordion-wrap '
                f'kt-accordion-id-{escape(unique)}">{self._tab_panes(comp_attrs)}</div>'
            )

        if block_name == "kadence/posts":
            return (
                '<div class="wp-block-kadence-posts kt-posts-wrap '
                f'kt-posts-id-{escape(unique)}"></div>'
            )

        # core/* fall-through
        if block_name == "core/paragraph":
            # Rich-text sources ship ready-made HTML; keep it verbatim.
            return content if "<" in content else f"<p>{escape(content)}</p>"
        if block_name == "core/list":
            items = comp_attrs.get("items")
            if isinstance(items, list) and items:
                lis = "".join(
                    f"<li>{escape(str(item.get('text', '') or ''))}</li>"
                    for item in items
                    if isinstance(item, dict)
                )
            else:
                lis = f"<li>{escape(content)}</li>"
            return f'<ul class="wp-block-list">{lis}</ul>'
        if block_name == "core/quote":
            cite_parts = [
                str(comp_attrs[key]).strip()
                for key in ("author", "cite", "job_title")
                if comp_attrs.get(key)
            ]
            cite = (
                f"<cite>{escape(', '.join(cite_parts))}</cite>" if cite_parts else ""
            )
            return (
                f'<blockquote class="wp-block-quote"><p>{escape(content)}</p>'
                f"{cite}</blockquote>"
            )
        if block_name == "core/code":
            return f'<pre class="wp-block-code"><code>{escape(content)}</code></pre>'
        if block_name == "core/html":
            return content
        if block_name == "core/video":
            url = comp_attrs.get("url") or comp_attrs.get("src") or ""
            return f'<figure class="wp-block-video"><video controls src="{escape(url)}"></video></figure>'
        if block_name == "core/image":
            url = comp_attrs.get("image_url") or comp_attrs.get("src") or ""
            alt = comp_attrs.get("alt_text") or comp_attrs.get("alt") or ""
            return f'<figure class="wp-block-image"><img src="{escape(url)}" alt="{escape(alt)}"/></figure>'
        if block_name == "core/gallery":
            figures = "".join(
                '<figure class="wp-block-image">'
                f'<img src="{escape(str(image.get("url", "") or ""))}" '
                f'alt="{escape(str(image.get("alt", "") or ""))}"/></figure>'
                for image in (comp_attrs.get("images") or [])
                if isinstance(image, dict)
            )
            return f'<figure class="wp-block-gallery has-nested-images">{figures}</figure>'

        return escape(content)

    def _tab_panes(self, comp_attrs: Dict[str, Any]) -> str:
        panes = []
        for item in comp_attrs.get("tabs") or []:
            if not isinstance(item, dict):
                continue
            title = str(item.get("tab_title", "") or "")
            body = str(item.get("tab_content", "") or "")
            # tab_content is rich text — keep it verbatim.
            panes.append(
                '<div class="kt-tab-inner-content">'
                f'<div class="kt-tab-title">{escape(title)}</div>{body}</div>'
            )
        return "".join(panes)

    def _denormalize_attributes(self, attributes: Dict[str, Any]) -> Dict[str, Any]:
        out: Dict[str, Any] = {}

        if "text-align" in attributes:
            out["align"] = attributes["text-align"]
        if "class" in attributes:
            out["className"] = attributes["class"]
        if "id" in attributes:
            out["anchor"] = attributes["id"]
        if "color" in attributes:
            out["color"] = attributes["color"]
        if "background-color" in attributes:
            out["background"] = attributes["background-color"]

        padding = [
            attributes.get("padding-top"),
            attributes.get("padding-right"),
            attributes.get("padding-bottom"),
            attributes.get("padding-left"),
        ]
        if any(v not in (None, "") for v in padding):
            out["padding"] = ["" if v in (None, "") else str(v) for v in padding]

        margin = [
            attributes.get("margin-top"),
            attributes.get("margin-right"),
            attributes.get("margin-bottom"),
            attributes.get("margin-left"),
        ]
        if any(v not in (None, "") for v in margin):
            out["margin"] = ["" if v in (None, "") else str(v) for v in margin]

        return out

    def _delimiter(self, block_name: str, attrs: Dict[str, Any]) -> str:
        # Kadence retains its namespace prefix (only core/* strips on serialization).
        # core/* blocks emitted by this converter should mirror Gutenberg canonical form.
        name = block_name[5:] if block_name.startswith("core/") else block_name
        if attrs:
            attrs_json = json.dumps(attrs, separators=(",", ":"))
            return f"<!-- wp:{name} {attrs_json} -->"
        return f"<!-- wp:{name} -->"

    def _closing(self, block_name: str) -> str:
        name = block_name[5:] if block_name.startswith("core/") else block_name
        return f"<!-- /wp:{name} -->"

    def _next_unique_id(self) -> str:
        self._id_counter += 1
        suffix = hashlib.md5(str(self._id_counter).encode("utf-8")).hexdigest()[:4]
        return f"_kb{self._id_counter:03d}-{suffix}"
