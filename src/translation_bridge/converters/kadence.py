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


# Upstream framework version (Kadence Blocks plugin) this converter is
# calibrated against. Theme template parts (Kadence Theme 1.5.x) share the
# same kadence/* namespace and are emitted through the same code path.
TARGET_CMS_VERSION: str = "3.7.2"


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
        # No Kadence-namespace equivalent — fall through to core/*.
        "text": "core/paragraph",
        "paragraph": "core/paragraph",
        "list": "core/list",
        "quote": "core/quote",
        "code": "core/code",
        "html": "core/html",
        "video": "core/video",
    }

    def __init__(self) -> None:
        self._id_counter = 0

    # --- Public surface -------------------------------------------------

    def convert(self, data: Any) -> str:
        """Convert universal data to a Kadence Blocks markup string."""
        if isinstance(data, dict):
            if "elements" in data and isinstance(data["elements"], list):
                return "".join(self._convert_component(c) for c in data["elements"])
            return self._convert_component(data)
        if isinstance(data, list):
            return "".join(self._convert_component(c) for c in data)
        return ""

    def get_framework(self) -> str:
        return "kadence"

    def get_supported_types(self) -> List[str]:
        return [
            "row", "container", "column",
            "heading", "button", "icon", "image", "spacer", "divider",
            "infobox", "card", "tabs", "accordion", "posts",
            "text", "paragraph", "list", "quote", "code", "html", "video",
        ]

    def supports_type(self, type_name: str) -> bool:
        return type_name in self.get_supported_types()

    def get_fallback(self, component: Dict[str, Any]) -> str:
        content = component.get("content", "")
        return f"<!-- wp:html -->\n{content}\n<!-- /wp:html -->\n\n"

    # --- Internal -------------------------------------------------------

    def _convert_component(self, component: Dict[str, Any]) -> str:
        if not isinstance(component, dict):
            return ""

        comp_type = component.get("type", "")

        if comp_type in ("container", "row"):
            return self._convert_rowlayout(component)
        if comp_type == "column":
            return self._convert_column(component)
        return self._convert_block(component)

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
            return self.get_fallback(component)

        attrs = self._denormalize_attributes(component.get("attributes", {}))
        attrs = self._lift_content_fields(block_name, attrs, component)

        # Every kadence/* block requires a uniqueID.
        if block_name.startswith("kadence/"):
            attrs = {"uniqueID": self._next_unique_id(), **attrs}

        inner_html = self._inner_html(block_name, component, attrs)

        opening = self._delimiter(block_name, attrs)
        closing = self._closing(block_name)
        return f"{opening}\n{inner_html}\n{closing}\n\n"

    def _lift_content_fields(
        self, block_name: str, attrs: Dict[str, Any], component: Dict[str, Any]
    ) -> Dict[str, Any]:
        comp_attrs = component.get("attributes", {}) or {}

        if block_name == "kadence/advancedheading":
            attrs["level"] = int(comp_attrs.get("level", 2))
        elif block_name == "kadence/image":
            if comp_attrs.get("src"):
                attrs["url"] = comp_attrs["src"]
            if comp_attrs.get("alt"):
                attrs["alt"] = comp_attrs["alt"]
        elif block_name == "kadence/spacer":
            attrs["spacerHeight"] = int(comp_attrs.get("height", 40))
        elif block_name == "kadence/icon":
            icon_name = comp_attrs.get("icon")
            if icon_name:
                attrs["icons"] = [{"icon": str(icon_name)}]
        elif block_name == "core/image":
            if comp_attrs.get("src"):
                attrs["url"] = comp_attrs["src"]
            if comp_attrs.get("alt"):
                attrs["alt"] = comp_attrs["alt"]

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
            url = comp_attrs.get("href", "#")
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
            url = comp_attrs.get("src", "")
            alt = comp_attrs.get("alt", "")
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
            return (
                f'<div class="wp-block-kadence-infobox kt-info-box-{escape(unique)}">'
                f'<div class="kt-blocks-info-box-text">{escape(content)}</div></div>'
            )

        if block_name == "kadence/tabs":
            return (
                '<div class="wp-block-kadence-tabs kt-tabs-wrap '
                f'kt-tabs-id-{escape(unique)}"></div>'
            )

        if block_name == "kadence/accordion":
            return (
                '<div class="wp-block-kadence-accordion kt-accordion-wrap '
                f'kt-accordion-id-{escape(unique)}"></div>'
            )

        if block_name == "kadence/posts":
            return (
                '<div class="wp-block-kadence-posts kt-posts-wrap '
                f'kt-posts-id-{escape(unique)}"></div>'
            )

        # core/* fall-through
        if block_name == "core/paragraph":
            return f"<p>{escape(content)}</p>"
        if block_name == "core/list":
            return f'<ul class="wp-block-list"><li>{escape(content)}</li></ul>'
        if block_name == "core/quote":
            return f'<blockquote class="wp-block-quote"><p>{escape(content)}</p></blockquote>'
        if block_name == "core/code":
            return f'<pre class="wp-block-code"><code>{escape(content)}</code></pre>'
        if block_name == "core/html":
            return content
        if block_name == "core/video":
            url = comp_attrs.get("src", "")
            return f'<figure class="wp-block-video"><video controls src="{escape(url)}"></video></figure>'
        if block_name == "core/image":
            url = comp_attrs.get("src", "")
            alt = comp_attrs.get("alt", "")
            return f'<figure class="wp-block-image"><img src="{escape(url)}" alt="{escape(alt)}"/></figure>'

        return escape(content)

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
