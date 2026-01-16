"""
Translation Bridge v4 - Gutenberg Block Converter.

Converts universal/parsed data TO WordPress Gutenberg block format.
Generates proper block HTML with delimited comments.
"""

from typing import Any, Dict, List, Optional
from html import escape
import json


class GutenbergConverter:
    """
    Converts parsed content to Gutenberg block format.

    Gutenberg block format:
    <!-- wp:block-name {"attrs": "values"} -->
    <element>content</element>
    <!-- /wp:block-name -->
    """

    # Universal type to Gutenberg block mapping
    BLOCK_TYPE_MAP = {
        "heading": "core/heading",
        "text": "core/paragraph",
        "paragraph": "core/paragraph",
        "image": "core/image",
        "button": "core/button",
        "buttons": "core/buttons",
        "divider": "core/separator",
        "spacer": "core/spacer",
        "icon": "core/image",
        "list": "core/list",
        "quote": "core/quote",
        "video": "core/embed",
        "gallery": "core/gallery",
        "columns": "core/columns",
        "column": "core/column",
        "group": "core/group",
        "cover": "core/cover",
        "table": "core/table",
        "html": "core/html",
        "shortcode": "core/shortcode",
    }

    def __init__(self):
        pass

    def convert(self, data: Any) -> str:
        """
        Convert universal data to Gutenberg block string.

        Args:
            data: Universal component data or list of components

        Returns:
            Gutenberg block HTML string
        """
        return self._convert_to_blocks(data)

    def _convert_to_blocks(self, data: Any) -> str:
        """Convert data to Gutenberg block structure."""
        if isinstance(data, dict):
            # Check if it has elements array
            if "elements" in data:
                return self._convert_elements(data["elements"])

            # Single component
            return self._convert_component(data)

        elif isinstance(data, list):
            return self._convert_elements(data)

        return ""

    def _convert_elements(self, elements: List[Dict[str, Any]]) -> str:
        """Convert a list of elements to blocks."""
        blocks = []

        for element in elements:
            el_type = element.get("elType", "")

            if el_type == "section" or el_type == "container":
                blocks.append(self._convert_section(element))
            elif el_type == "column":
                blocks.append(self._convert_column(element))
            elif el_type == "widget":
                blocks.append(self._convert_widget(element))
            else:
                blocks.append(self._convert_component(element))

        return "\n\n".join(blocks)

    def _convert_section(self, section: Dict[str, Any]) -> str:
        """Convert a section to Gutenberg group/columns block."""
        settings = section.get("settings", {})
        children = section.get("elements", [])

        # Determine if this should be columns or group
        if len(children) > 1 and all(c.get("elType") == "column" for c in children):
            return self._build_columns_block(children, settings)
        else:
            return self._build_group_block(children, settings)

    def _convert_column(self, column: Dict[str, Any]) -> str:
        """Convert a column to Gutenberg column block."""
        settings = column.get("settings", {})
        children = column.get("elements", [])

        # Convert children
        inner_blocks = [
            self._convert_widget(child) if child.get("elType") == "widget"
            else self._convert_component(child)
            for child in children
        ]

        inner_content = "\n".join(inner_blocks)

        # Calculate width
        col_size = settings.get("_column_size", 100)
        width_percent = col_size

        attrs = {}
        if width_percent != 100:
            attrs["width"] = f"{width_percent}%"

        return self._build_block(
            "core/column",
            attrs,
            f'<div class="wp-block-column">\n{inner_content}\n</div>'
        )

    def _convert_widget(self, widget: Dict[str, Any]) -> str:
        """Convert an Elementor widget to Gutenberg block."""
        widget_type = widget.get("widgetType", "text")
        settings = widget.get("settings", {})

        return self._build_widget_block(widget_type, settings)

    def _convert_component(self, component: Dict[str, Any]) -> str:
        """Convert a generic component to Gutenberg block."""
        comp_type = component.get("type", component.get("widgetType", "text"))
        attrs = component.get("attributes", component.get("settings", {}))
        content = component.get("content", "")

        return self._build_widget_block(comp_type, attrs, content)

    def _build_widget_block(self, widget_type: str, settings: Dict[str, Any], content: str = "") -> str:
        """Build appropriate Gutenberg block for widget type."""
        if widget_type == "heading":
            return self._build_heading_block(settings)
        elif widget_type in ["text", "text-editor", "paragraph"]:
            return self._build_paragraph_block(settings, content)
        elif widget_type == "image":
            return self._build_image_block(settings)
        elif widget_type == "button":
            return self._build_button_block(settings)
        elif widget_type in ["divider", "spacer"]:
            return self._build_separator_block(settings)
        elif widget_type == "video":
            return self._build_video_block(settings)
        elif widget_type == "gallery":
            return self._build_gallery_block(settings)
        elif widget_type == "tabs":
            return self._build_tabs_as_group(settings)
        elif widget_type == "accordion":
            return self._build_accordion_as_group(settings)
        elif widget_type == "html":
            return self._build_html_block(settings, content)
        elif widget_type == "icon-list":
            return self._build_list_block(settings)
        elif widget_type == "testimonial":
            return self._build_quote_block(settings)
        else:
            # Default to paragraph with any text content
            text = content or settings.get("title", settings.get("text", settings.get("editor", "")))
            return self._build_paragraph_block({"editor": text}, content)

    def _build_heading_block(self, settings: Dict[str, Any]) -> str:
        """Build core/heading block."""
        title = settings.get("title", "")
        level = settings.get("header_size", "h2")
        level_num = int(level[1]) if level.startswith("h") and len(level) == 2 else 2

        align = settings.get("align", "")

        attrs = {"level": level_num}
        if align:
            attrs["textAlign"] = align

        html = f'<{level} class="wp-block-heading">{escape(title)}</{level}>'

        return self._build_block("core/heading", attrs, html)

    def _build_paragraph_block(self, settings: Dict[str, Any], content: str = "") -> str:
        """Build core/paragraph block."""
        text = content or settings.get("editor", settings.get("text", ""))

        # Preserve HTML if present, otherwise escape
        if "<" in text and ">" in text:
            html = f'<p>{text}</p>'
        else:
            html = f'<p>{escape(text)}</p>'

        attrs = {}
        if settings.get("align"):
            attrs["align"] = settings["align"]

        return self._build_block("core/paragraph", attrs, html)

    def _build_image_block(self, settings: Dict[str, Any]) -> str:
        """Build core/image block."""
        image = settings.get("image", {})
        url = image.get("url", "") if isinstance(image, dict) else ""
        alt = image.get("alt", "") if isinstance(image, dict) else ""
        img_id = image.get("id", "") if isinstance(image, dict) else ""

        attrs = {}
        if img_id:
            attrs["id"] = img_id

        html = f'<figure class="wp-block-image"><img src="{escape(url)}" alt="{escape(alt)}"/></figure>'

        return self._build_block("core/image", attrs, html)

    def _build_button_block(self, settings: Dict[str, Any]) -> str:
        """Build core/button block (wrapped in core/buttons)."""
        text = settings.get("text", "Click Here")
        link = settings.get("link", {})
        url = link.get("url", "#") if isinstance(link, dict) else "#"

        # Button attributes
        button_attrs = {}
        if url:
            button_attrs["url"] = url

        button_html = f'<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="{escape(url)}">{escape(text)}</a></div>'
        button_block = self._build_block("core/button", button_attrs, button_html)

        # Wrap in buttons container
        buttons_html = f'<div class="wp-block-buttons">\n{button_block}\n</div>'
        return self._build_block("core/buttons", {}, buttons_html)

    def _build_separator_block(self, settings: Dict[str, Any]) -> str:
        """Build core/separator block."""
        attrs = {}
        html = '<hr class="wp-block-separator has-alpha-channel-opacity"/>'
        return self._build_block("core/separator", attrs, html)

    def _build_video_block(self, settings: Dict[str, Any]) -> str:
        """Build core/embed block for video."""
        url = settings.get("youtube_url", settings.get("video_url", ""))

        # Determine provider
        provider = "youtube"
        if "vimeo" in url:
            provider = "vimeo"

        attrs = {
            "url": url,
            "type": "video",
            "providerNameSlug": provider,
        }

        html = f'''<figure class="wp-block-embed is-type-video is-provider-{provider}">
<div class="wp-block-embed__wrapper">
{escape(url)}
</div>
</figure>'''

        return self._build_block(f"core/embed", attrs, html)

    def _build_gallery_block(self, settings: Dict[str, Any]) -> str:
        """Build core/gallery block."""
        gallery = settings.get("gallery", settings.get("wp_gallery", []))

        images = []
        for img in gallery:
            if isinstance(img, dict):
                url = img.get("url", "")
                img_id = img.get("id", "")
                images.append({"id": img_id, "url": url})

        attrs = {"linkTo": "none"}
        if images:
            attrs["ids"] = [img["id"] for img in images if img.get("id")]

        # Build gallery HTML
        img_tags = "\n".join([
            f'<figure class="wp-block-image"><img src="{escape(img["url"])}" alt=""/></figure>'
            for img in images
        ])

        html = f'<figure class="wp-block-gallery has-nested-images columns-default">\n{img_tags}\n</figure>'

        return self._build_block("core/gallery", attrs, html)

    def _build_tabs_as_group(self, settings: Dict[str, Any]) -> str:
        """Build tabs as a group with headings and paragraphs."""
        tabs = settings.get("tabs", [])

        blocks = []
        for tab in tabs:
            title = tab.get("tab_title", "Tab")
            content = tab.get("tab_content", "")

            # Heading for tab title
            heading = self._build_heading_block({"title": title, "header_size": "h3"})
            blocks.append(heading)

            # Content
            if content:
                para = self._build_paragraph_block({"editor": content})
                blocks.append(para)

        inner = "\n".join(blocks)
        return self._build_group_block_raw(inner, {"className": "tabs-converted"})

    def _build_accordion_as_group(self, settings: Dict[str, Any]) -> str:
        """Build accordion as details/summary elements."""
        items = settings.get("tabs", [])

        html_parts = []
        for item in items:
            title = item.get("tab_title", "Item")
            content = item.get("tab_content", "")
            html_parts.append(f'<details><summary>{escape(title)}</summary><p>{content}</p></details>')

        inner_html = "\n".join(html_parts)
        return self._build_block("core/html", {}, inner_html)

    def _build_html_block(self, settings: Dict[str, Any], content: str = "") -> str:
        """Build core/html block."""
        html = content or settings.get("html", "")
        return self._build_block("core/html", {}, html)

    def _build_list_block(self, settings: Dict[str, Any]) -> str:
        """Build core/list block."""
        items = settings.get("icon_list", [])

        list_items = []
        for item in items:
            if isinstance(item, dict):
                text = item.get("text", "")
                list_items.append(f'<li>{escape(text)}</li>')

        html = f'<ul class="wp-block-list">\n{"".join(list_items)}\n</ul>'
        return self._build_block("core/list", {}, html)

    def _build_quote_block(self, settings: Dict[str, Any]) -> str:
        """Build core/quote block."""
        content = settings.get("testimonial_content", "")
        author = settings.get("testimonial_name", "")

        html = f'''<blockquote class="wp-block-quote">
<p>{content}</p>
<cite>{escape(author)}</cite>
</blockquote>'''

        return self._build_block("core/quote", {}, html)

    def _build_columns_block(self, columns: List[Dict[str, Any]], settings: Dict[str, Any]) -> str:
        """Build core/columns block with nested columns."""
        column_blocks = []
        for col in columns:
            column_blocks.append(self._convert_column(col))

        inner = "\n".join(column_blocks)
        html = f'<div class="wp-block-columns">\n{inner}\n</div>'

        return self._build_block("core/columns", {}, html)

    def _build_group_block(self, children: List[Dict[str, Any]], settings: Dict[str, Any]) -> str:
        """Build core/group block with children."""
        inner_blocks = []
        for child in children:
            el_type = child.get("elType", "")
            if el_type == "widget":
                inner_blocks.append(self._convert_widget(child))
            elif el_type == "column":
                inner_blocks.append(self._convert_column(child))
            else:
                inner_blocks.append(self._convert_component(child))

        inner = "\n".join(inner_blocks)
        return self._build_group_block_raw(inner, {})

    def _build_group_block_raw(self, inner_content: str, attrs: Dict[str, Any]) -> str:
        """Build core/group block with raw inner content."""
        html = f'<div class="wp-block-group">\n{inner_content}\n</div>'
        return self._build_block("core/group", attrs, html)

    def _build_block(self, block_type: str, attrs: Dict[str, Any], html: str) -> str:
        """Build a Gutenberg block with delimited comments."""
        if attrs:
            attrs_json = json.dumps(attrs, separators=(',', ':'))
            return f'<!-- wp:{block_type} {attrs_json} -->\n{html}\n<!-- /wp:{block_type} -->'
        else:
            return f'<!-- wp:{block_type} -->\n{html}\n<!-- /wp:{block_type} -->'

    def get_framework(self) -> str:
        """Return framework name."""
        return "gutenberg"

    def get_supported_types(self) -> List[str]:
        """Return list of supported block types."""
        return list(self.BLOCK_TYPE_MAP.keys())
