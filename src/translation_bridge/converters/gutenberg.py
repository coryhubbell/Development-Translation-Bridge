"""
Translation Bridge v4 - Gutenberg Block Converter.

Converts Elementor JSON (or universal element dicts) into WordPress Gutenberg
block markup — the HTML+comment-delimited format that the block editor parses.

Mirrors the semantics of the PHP DEVTB_Gutenberg_Converter introduced in v4.3.4:
every widget the Elementor parser produces is either mapped to a native Gutenberg
block, expanded into a compound group of native blocks (tabs, accordion, card, cta,
counter, testimonial, pricing-table, alert), or — when there is no clean
equivalent — preserved as core/html with a visible `devtb` annotation comment.
There is no silent collapse to an empty paragraph.

Canonical WP serialization rules preserved from v4.2.0:
- Block delimiters drop the `core/` namespace (`<!-- wp:heading -->`, not `<!-- wp:core/heading -->`).
- core/list now contains core/list-item innerBlocks (WP 6.0+).
- Paragraph text alignment uses `textAlign`, not block-level `align`.
- core/heading carries `class="wp-block-heading"` (WP 6.3+ canonical).
- core/button carries `class="wp-block-button__link wp-element-button"` (WP 6.1+ theme.json).
- core/separator carries `class="wp-block-separator has-alpha-channel-opacity"` (WP 6.5+).
"""

from typing import Any, Dict, Iterable, List, Optional
from html import escape
import json
import re


# Upstream framework (WordPress core) version this converter is calibrated against.
TARGET_CMS_VERSION: str = "6.9.0"


class GutenbergConverter:
    """Convert Elementor / universal data to Gutenberg block markup."""

    # widgetType -> dispatch category. Anything not listed falls through to a
    # paragraph (for plain text widgets) or to the marker fallback.
    SIMPLE_BLOCK_FOR_WIDGET: Dict[str, str] = {
        "heading": "core/heading",
        "text-editor": "core/paragraph",
        "text": "core/paragraph",
        "paragraph": "core/paragraph",
        "image": "core/image",
        "button": "core/button",
        "divider": "core/separator",
        "spacer": "core/spacer",
        "icon": "core/html",
        "html": "core/html",
        "shortcode": "core/shortcode",
        "audio": "core/audio",
        "video": "core/video",
        "icon-list": "core/list",
        "image-gallery": "core/gallery",
        "basic-gallery": "core/gallery",
        "gallery": "core/gallery",
        "blockquote": "core/quote",
        "social-icons": "core/social-links",
        "share-buttons": "core/social-links",
        "nav-menu": "core/navigation",
    }

    COMPOUND_WIDGETS = {
        "tabs",
        "accordion",
        "toggle",
        "icon-box",
        "image-box",
        "flip-box",
        "card",
        "call-to-action",
        "counter",
        "testimonial",
        "price-table",
        "price-list",
        "alert",
    }

    MARKER_WIDGETS = {
        "form",
        "login",
        "slider",
        "slides",
        "image-carousel",
        "testimonial-carousel",
        "countdown",
        "portfolio",
        "posts",
        "table-of-contents",
        "google_maps",
        "progress",
        "star-rating",
        "animated-headline",
    }

    def __init__(self) -> None:
        pass

    # ----- entry points -----

    def convert(self, data: Any) -> str:
        """Convert Elementor data (dict, list, or single element) to Gutenberg block markup."""
        return self._convert(data)

    def get_framework(self) -> str:
        return "gutenberg"

    def get_target_cms_version(self) -> str:
        return TARGET_CMS_VERSION

    def get_supported_widgets(self) -> List[str]:
        return sorted(
            set(self.SIMPLE_BLOCK_FOR_WIDGET.keys())
            | self.COMPOUND_WIDGETS
            | self.MARKER_WIDGETS
        )

    # ----- dispatch -----

    def _convert(self, data: Any) -> str:
        if isinstance(data, dict):
            if "elements" in data and isinstance(data["elements"], list):
                return self._convert_elements(data["elements"])
            el_type = data.get("elType")
            if el_type in ("section", "container"):
                return self._convert_section(data)
            if el_type == "column":
                return self._convert_column(data)
            if el_type == "widget":
                return self._convert_widget(data)
            return self._convert_component(data)
        if isinstance(data, list):
            return self._convert_elements(data)
        return ""

    def _convert_elements(self, elements: Iterable[Dict[str, Any]]) -> str:
        blocks: List[str] = []
        for element in elements:
            if not isinstance(element, dict):
                continue
            el_type = element.get("elType")
            if el_type in ("section", "container"):
                blocks.append(self._convert_section(element))
            elif el_type == "column":
                blocks.append(self._convert_column(element))
            elif el_type == "widget":
                blocks.append(self._convert_widget(element))
            else:
                blocks.append(self._convert_component(element))
        return "\n\n".join(b for b in blocks if b)

    def _convert_section(self, section: Dict[str, Any]) -> str:
        settings = section.get("settings", {}) or {}
        children = section.get("elements", []) or []

        if children and all(c.get("elType") == "column" for c in children if isinstance(c, dict)):
            return self._build_columns_block(children, settings)
        return self._build_group_block(children, settings)

    def _convert_column(self, column: Dict[str, Any]) -> str:
        settings = column.get("settings", {}) or {}
        children = column.get("elements", []) or []

        inner_blocks: List[str] = []
        for child in children:
            if not isinstance(child, dict):
                continue
            el_type = child.get("elType")
            if el_type == "widget":
                inner_blocks.append(self._convert_widget(child))
            elif el_type in ("section", "container"):
                inner_blocks.append(self._convert_section(child))
            elif el_type == "column":
                inner_blocks.append(self._convert_column(child))
            else:
                inner_blocks.append(self._convert_component(child))

        inner_content = "\n".join(b for b in inner_blocks if b)

        attrs = self._denormalize_settings(settings)
        col_size = settings.get("_column_size", 100)
        try:
            col_size_num = float(col_size)
        except (TypeError, ValueError):
            col_size_num = 100.0
        if col_size_num != 100:
            # Canonical core/column width is a string with unit (e.g. "50%").
            attrs["width"] = f"{col_size_num}%"

        html = f'<div class="wp-block-column">\n{inner_content}\n</div>'
        return self._build_block("core/column", attrs, html)

    def _convert_widget(self, widget: Dict[str, Any]) -> str:
        widget_type = widget.get("widgetType", "")
        settings = widget.get("settings", {}) or {}
        children = widget.get("elements", []) or []
        component = {
            "widgetType": widget_type,
            "settings": settings,
            "children": children,
            "elementor_id": widget.get("id"),
        }

        if widget_type in self.COMPOUND_WIDGETS:
            return self._convert_compound(component)
        if widget_type in self.MARKER_WIDGETS:
            return self._convert_as_marker(component)
        if widget_type in self.SIMPLE_BLOCK_FOR_WIDGET:
            return self._convert_simple_widget(component)
        # Unknown widget type — preserve as a marker, never collapse to an empty paragraph.
        return self._convert_as_marker(component)

    def _convert_component(self, component: Dict[str, Any]) -> str:
        comp_type = component.get("type", component.get("widgetType", ""))
        settings = component.get("attributes", component.get("settings", {})) or {}
        content = component.get("content", "")
        universal_to_widget = {
            "text": "text-editor",
            "paragraph": "text-editor",
            "heading": "heading",
            "image": "image",
            "button": "button",
            "list": "icon-list",
            "gallery": "gallery",
            "video": "video",
            "audio": "audio",
            "divider": "divider",
            "spacer": "spacer",
            "blockquote": "blockquote",
            "quote": "blockquote",
            "html": "html",
            "icon": "icon",
            "card": "card",
            "cta": "call-to-action",
            "counter": "counter",
            "testimonial": "testimonial",
            "pricing-table": "price-table",
            "alert": "alert",
            "tabs": "tabs",
            "accordion": "accordion",
            "social-icons": "social-icons",
            "nav": "nav-menu",
            "table": "html",
            "form": "form",
            "slider": "slider",
            "countdown": "countdown",
            "portfolio": "portfolio",
            "toc": "table-of-contents",
            "map": "google_maps",
            "progress": "progress",
            "rating": "star-rating",
        }
        widget_type = universal_to_widget.get(comp_type, comp_type)
        synthetic_settings = settings if isinstance(settings, dict) else {}
        if content and widget_type in ("text-editor", "text"):
            synthetic_settings = {**synthetic_settings, "editor": content}
        return self._convert_widget({
            "elType": "widget",
            "widgetType": widget_type,
            "settings": synthetic_settings,
            "elements": component.get("children", []),
        })

    # ----- simple 1:1 widgets -----

    def _convert_simple_widget(self, component: Dict[str, Any]) -> str:
        widget_type = component["widgetType"]
        settings = component["settings"]

        if widget_type == "heading":
            return self._build_heading_block(settings)
        if widget_type in ("text-editor", "text", "paragraph"):
            return self._build_paragraph_block(settings)
        if widget_type == "image":
            return self._build_image_block_from_settings(settings)
        if widget_type == "button":
            return self._build_buttons_block_from_settings(settings)
        if widget_type == "divider":
            return self._build_separator_block(settings)
        if widget_type == "spacer":
            return self._build_spacer_block(settings)
        if widget_type == "icon":
            return self._build_icon_html_block(settings)
        if widget_type == "html":
            return self._build_html_block(settings)
        if widget_type == "shortcode":
            return self._build_shortcode_block(settings)
        if widget_type == "audio":
            return self._build_audio_block(settings)
        if widget_type == "video":
            return self._build_video_block(settings)
        if widget_type == "icon-list":
            return self._build_list_block(settings)
        if widget_type in ("image-gallery", "basic-gallery", "gallery"):
            return self._build_gallery_block(settings)
        if widget_type == "blockquote":
            return self._build_blockquote_block(settings)
        if widget_type in ("social-icons", "share-buttons"):
            return self._build_social_links_block(settings)
        if widget_type == "nav-menu":
            return self._build_navigation_block(settings)

        return self._convert_as_marker(component)

    def _build_heading_block(self, settings: Dict[str, Any]) -> str:
        title = settings.get("title", "")
        level = self._parse_heading_level(settings.get("header_size", "h2"))
        attrs: Dict[str, Any] = {"level": level}
        align = settings.get("align") or settings.get("text_align")
        if align:
            attrs["textAlign"] = align
        attrs.update(self._denormalize_settings(settings))
        # `wp-block-heading` is canonical since WP 6.3.
        html = f'<h{level} class="wp-block-heading">{escape(str(title))}</h{level}>'
        return self._build_block("core/heading", attrs, html)

    def _build_paragraph_block(self, settings: Dict[str, Any], content: str = "") -> str:
        text = content or settings.get("editor", settings.get("text", ""))
        attrs = self._denormalize_settings(settings)
        align = settings.get("align") or settings.get("text_align")
        if align:
            # core/paragraph uses `textAlign` for text alignment (block-level `align` means
            # full/wide alignment in canonical 6.5+ schema).
            attrs["textAlign"] = align
        if self._looks_like_html(text):
            html = f"<p>{text}</p>"
        else:
            html = f"<p>{escape(str(text))}</p>"
        return self._build_block("core/paragraph", attrs, html)

    def _build_image_block_from_settings(self, settings: Dict[str, Any]) -> str:
        image = settings.get("image") or {}
        url = image.get("url", "") if isinstance(image, dict) else ""
        alt = image.get("alt", "") if isinstance(image, dict) else ""
        image_id = image.get("id") if isinstance(image, dict) else None
        caption = settings.get("caption", "")

        attrs: Dict[str, Any] = {}
        if image_id:
            attrs["id"] = image_id
        attrs.update(self._denormalize_settings(settings))

        caption_html = (
            f'<figcaption class="wp-element-caption">{escape(str(caption))}</figcaption>'
            if caption
            else ""
        )
        html = (
            f'<figure class="wp-block-image">'
            f'<img src="{escape(str(url))}" alt="{escape(str(alt))}"/>'
            f"{caption_html}</figure>"
        )
        return self._build_block("core/image", attrs, html)

    def _build_buttons_block_from_settings(self, settings: Dict[str, Any]) -> str:
        text = settings.get("text", "Click Here")
        link = settings.get("link") or {}
        url = link.get("url", "#") if isinstance(link, dict) else "#"
        target = "_blank" if (isinstance(link, dict) and link.get("is_external")) else ""
        rel = "nofollow" if (isinstance(link, dict) and link.get("nofollow")) else ""

        button_attrs: Dict[str, Any] = {}
        if url and url != "#":
            button_attrs["url"] = url
        if target:
            button_attrs["linkTarget"] = target
        if rel:
            button_attrs["rel"] = rel

        target_attr = f' target="{escape(target)}"' if target else ""
        rel_attr = f' rel="{escape(rel)}"' if rel else ""
        # `wp-element-button` is required by theme.json element-styling pipeline since WP 6.1.
        btn_inner = (
            f'<div class="wp-block-button">'
            f'<a class="wp-block-button__link wp-element-button" href="{escape(str(url))}"'
            f"{target_attr}{rel_attr}>{escape(str(text))}</a></div>"
        )
        button_block = self._build_block("core/button", button_attrs, btn_inner)

        wrapper = f'<div class="wp-block-buttons">\n{button_block}\n</div>'
        return self._build_block("core/buttons", {}, wrapper)

    def _build_separator_block(self, settings: Dict[str, Any]) -> str:
        # `has-alpha-channel-opacity` is canonical since WP 6.5.
        return self._build_block(
            "core/separator",
            {},
            '<hr class="wp-block-separator has-alpha-channel-opacity"/>',
        )

    def _build_spacer_block(self, settings: Dict[str, Any]) -> str:
        height = settings.get("space", {})
        size = height.get("size") if isinstance(height, dict) else height
        height_val = f"{size}px" if size else "100px"
        html = (
            f'<div style="height:{escape(str(height_val))}" aria-hidden="true" '
            f'class="wp-block-spacer"></div>'
        )
        return self._build_block("core/spacer", {}, html)

    def _build_icon_html_block(self, settings: Dict[str, Any]) -> str:
        selected = settings.get("selected_icon") or {}
        icon_class = ""
        if isinstance(selected, dict):
            icon_class = selected.get("value", "") or ""
        icon_class = icon_class or settings.get("icon", "") or ""
        html = (
            f'<i class="{escape(str(icon_class))}" aria-hidden="true"></i>'
            if icon_class
            else ""
        )
        return self._build_block("core/html", {}, html)

    def _build_html_block(self, settings: Dict[str, Any], content: str = "") -> str:
        html = content or settings.get("html", "")
        return self._build_block("core/html", {}, html)

    def _build_shortcode_block(self, settings: Dict[str, Any]) -> str:
        shortcode = settings.get("shortcode", "")
        return self._build_block("core/shortcode", {}, shortcode)

    def _build_audio_block(self, settings: Dict[str, Any]) -> str:
        link = settings.get("link") or settings.get("audio_url") or ""
        if isinstance(link, dict):
            url = link.get("url", "")
        else:
            url = link
        html = (
            f'<figure class="wp-block-audio"><audio controls src="{escape(str(url))}"></audio></figure>'
        )
        return self._build_block("core/audio", {"src": url} if url else {}, html)

    def _build_video_block(self, settings: Dict[str, Any]) -> str:
        url = settings.get("youtube_url") or settings.get("video_url") or settings.get("url") or ""
        provider = "vimeo" if "vimeo" in str(url) else "youtube"
        attrs = {
            "url": url,
            "type": "video",
            "providerNameSlug": provider,
        } if url else {}
        html = (
            f'<figure class="wp-block-embed is-type-video is-provider-{provider}">'
            f'<div class="wp-block-embed__wrapper">\n{escape(str(url))}\n</div></figure>'
        )
        block_name = "core/embed" if url else "core/video"
        return self._build_block(block_name, attrs, html)

    def _build_list_block(self, settings: Dict[str, Any]) -> str:
        items = settings.get("icon_list") or settings.get("items") or []
        if isinstance(items, str):
            try:
                items = json.loads(items)
            except (ValueError, TypeError):
                items = []
        # WP 6.0+ canonical shape: core/list contains core/list-item innerBlocks.
        item_blocks: List[str] = []
        for it in items if isinstance(items, list) else []:
            if not isinstance(it, dict):
                continue
            text = it.get("text", "")
            inner = f"<li>{escape(str(text))}</li>"
            item_blocks.append(self._build_block("core/list-item", {}, inner))
        html = (
            '<ul class="wp-block-list">\n'
            + "\n".join(item_blocks)
            + "\n</ul>"
        )
        return self._build_block("core/list", {}, html)

    def _build_gallery_block(self, settings: Dict[str, Any]) -> str:
        images = settings.get("wp_gallery") or settings.get("gallery") or settings.get("images") or []
        if isinstance(images, str):
            try:
                images = json.loads(images)
            except (ValueError, TypeError):
                images = []

        ids: List[int] = []
        figures: List[str] = []
        for img in images if isinstance(images, list) else []:
            if not isinstance(img, dict):
                continue
            url = img.get("url", "")
            alt = img.get("alt", "")
            try:
                if img.get("id"):
                    ids.append(int(img["id"]))
            except (TypeError, ValueError):
                pass
            figures.append(
                f'<figure class="wp-block-image">'
                f'<img src="{escape(str(url))}" alt="{escape(str(alt))}"/></figure>'
            )

        attrs: Dict[str, Any] = {"linkTo": settings.get("link_to", "none")}
        if ids:
            attrs["ids"] = ids
        html = (
            f'<figure class="wp-block-gallery has-nested-images columns-default">\n'
            f'{"".join(figures)}\n</figure>'
        )
        return self._build_block("core/gallery", attrs, html)

    def _build_blockquote_block(self, settings: Dict[str, Any]) -> str:
        body = settings.get("blockquote_content") or settings.get("content", "")
        cite = settings.get("author") or settings.get("cite") or ""
        rendered = body if self._looks_like_html(body) else f"<p>{escape(str(body))}</p>"
        cite_html = f"<cite>{escape(str(cite))}</cite>" if cite else ""
        html = f'<blockquote class="wp-block-quote">{rendered}{cite_html}</blockquote>'
        return self._build_block("core/quote", {}, html)

    def _build_social_links_block(self, settings: Dict[str, Any]) -> str:
        items = settings.get("social_icon_list") or settings.get("icons") or []
        if isinstance(items, str):
            try:
                items = json.loads(items)
            except (ValueError, TypeError):
                items = []
        inner = ""
        for item in items if isinstance(items, list) else []:
            if not isinstance(item, dict):
                continue
            link = item.get("link") or {}
            url = link.get("url", "") if isinstance(link, dict) else (link or "")
            service = item.get("social") or item.get("service") or "link"
            attrs_json = json.dumps(
                {"url": url, "service": service}, separators=(",", ":"), ensure_ascii=False
            )
            inner += f"<!-- wp:social-link {attrs_json} /-->"
        html = f'<ul class="wp-block-social-links">{inner}</ul>'
        return self._build_block("core/social-links", {}, html)

    def _build_navigation_block(self, settings: Dict[str, Any]) -> str:
        # core/navigation requires a menu reference; without one, emit the empty block so
        # the editor prompts the user to pick a menu rather than dropping the widget.
        return self._build_block("core/navigation", {}, "")

    # ----- compound widgets -----

    def _convert_compound(self, component: Dict[str, Any]) -> str:
        widget_type = component["widgetType"]
        if widget_type in ("tabs", "accordion", "toggle"):
            return self._convert_tabs_or_accordion(component)
        if widget_type in ("icon-box", "image-box", "flip-box", "card"):
            return self._convert_card(component)
        if widget_type == "call-to-action":
            return self._convert_cta(component)
        if widget_type == "counter":
            return self._convert_counter(component)
        if widget_type == "testimonial":
            return self._convert_testimonial(component)
        if widget_type in ("price-table", "price-list"):
            return self._convert_pricing_table(component)
        if widget_type == "alert":
            return self._convert_alert(component)
        return self._convert_as_marker(component)

    def _convert_tabs_or_accordion(self, component: Dict[str, Any]) -> str:
        settings = component["settings"]
        widget_type = component["widgetType"]
        items = settings.get("tabs") or settings.get("items") or []
        if isinstance(items, str):
            try:
                items = json.loads(items)
            except (ValueError, TypeError):
                items = []

        blocks: List[str] = []
        for item in items if isinstance(items, list) else []:
            if not isinstance(item, dict):
                continue
            title = item.get("tab_title") or item.get("title", "")
            body = item.get("tab_content") or item.get("content", "")
            blocks.append(self._build_heading_block({"title": title, "header_size": "h3"}))
            blocks.append(self._build_paragraph_block({"editor": body}))

        class_name = (
            "devtb-accordion-converted" if widget_type in ("accordion", "toggle") else "devtb-tabs-converted"
        )
        return self._wrap_in_group("\n".join(blocks), {"className": class_name})

    def _convert_card(self, component: Dict[str, Any]) -> str:
        settings = component["settings"]
        blocks: List[str] = []

        image = settings.get("image") or {}
        image_url = image.get("url", "") if isinstance(image, dict) else ""
        if image_url:
            blocks.append(self._build_image_block_from_settings(settings))

        title = settings.get("title_text") or settings.get("title") or ""
        if title:
            blocks.append(self._build_heading_block({"title": title, "header_size": "h3"}))

        description = settings.get("description_text") or settings.get("description") or ""
        if description:
            blocks.append(self._build_paragraph_block({"editor": description}))

        link = settings.get("link") or {}
        link_url = link.get("url", "") if isinstance(link, dict) else ""
        button_text = settings.get("button_text") or settings.get("link_text") or ""
        if link_url or button_text:
            blocks.append(
                self._build_buttons_block_from_settings(
                    {"text": button_text or "Learn more", "link": link or {"url": link_url}}
                )
            )

        return self._wrap_in_group("\n".join(blocks), {"className": "devtb-card-converted"})

    def _convert_cta(self, component: Dict[str, Any]) -> str:
        settings = component["settings"]
        blocks: List[str] = []

        title = settings.get("title") or settings.get("heading") or ""
        if title:
            blocks.append(self._build_heading_block({"title": title, "header_size": "h2"}))

        description = settings.get("description") or settings.get("description_text") or ""
        if description:
            blocks.append(self._build_paragraph_block({"editor": description}))

        button_text = settings.get("button_text") or settings.get("cta_text") or "Learn more"
        link = settings.get("link") or {}
        if isinstance(link, dict) and link.get("url"):
            blocks.append(
                self._build_buttons_block_from_settings({"text": button_text, "link": link})
            )

        return self._wrap_in_group("\n".join(blocks), {"className": "devtb-cta-converted"})

    def _convert_counter(self, component: Dict[str, Any]) -> str:
        settings = component["settings"]
        ending = settings.get("ending_number") or settings.get("number") or ""
        prefix = settings.get("prefix") or ""
        suffix = settings.get("suffix") or ""
        title = settings.get("title") or ""

        display = f"{prefix}{ending}{suffix}".strip()
        blocks: List[str] = []
        if display:
            blocks.append(self._build_heading_block({"title": display, "header_size": "h2"}))
        if title:
            blocks.append(self._build_paragraph_block({"editor": title}))

        return self._wrap_in_group("\n".join(blocks), {"className": "devtb-counter-converted"})

    def _convert_testimonial(self, component: Dict[str, Any]) -> str:
        settings = component["settings"]
        content = settings.get("testimonial_content", "")
        name = settings.get("testimonial_name", "")
        job = settings.get("testimonial_job", "")
        cite = f"{name}{', ' if name and job else ''}{job}".strip()

        body = content if self._looks_like_html(content) else f"<p>{escape(str(content))}</p>"
        cite_html = f"<cite>{escape(cite)}</cite>" if cite else ""
        html = f'<blockquote class="wp-block-quote">{body}{cite_html}</blockquote>'
        return self._build_block("core/quote", {}, html)

    def _convert_pricing_table(self, component: Dict[str, Any]) -> str:
        settings = component["settings"]
        blocks: List[str] = []

        title = settings.get("heading") or settings.get("title") or ""
        if title:
            blocks.append(self._build_heading_block({"title": title, "header_size": "h3"}))

        price = settings.get("price", "")
        currency = settings.get("currency_symbol", "")
        period = settings.get("period", "")
        price_display = f"{currency}{price}"
        if period:
            price_display += f" / {period}"
        if price_display.strip():
            blocks.append(self._build_heading_block({"title": price_display, "header_size": "h2"}))

        features = settings.get("features") or settings.get("items") or []
        if isinstance(features, str):
            try:
                features = json.loads(features)
            except (ValueError, TypeError):
                features = []
        if isinstance(features, list) and features:
            # Build a canonical core/list with core/list-item innerBlocks.
            item_blocks: List[str] = []
            for f in features:
                if not isinstance(f, dict):
                    continue
                text = f.get("item_text", f.get("text", ""))
                item_blocks.append(
                    self._build_block("core/list-item", {}, f"<li>{escape(str(text))}</li>")
                )
            blocks.append(
                self._build_block(
                    "core/list",
                    {},
                    '<ul class="wp-block-list">\n' + "\n".join(item_blocks) + "\n</ul>",
                )
            )

        button_text = settings.get("button_text") or "Get started"
        button_url = settings.get("button_url") or ""
        link = settings.get("link") or {}
        if not button_url and isinstance(link, dict):
            button_url = link.get("url", "")
        if button_url:
            blocks.append(
                self._build_buttons_block_from_settings(
                    {"text": button_text, "link": {"url": button_url}}
                )
            )

        return self._wrap_in_group("\n".join(blocks), {"className": "devtb-pricing-converted"})

    def _convert_alert(self, component: Dict[str, Any]) -> str:
        settings = component["settings"]
        alert_type = settings.get("alert_type") or settings.get("type") or "info"
        title = settings.get("alert_title", "")
        body = settings.get("alert_description", "") or component.get("content", "")

        blocks: List[str] = []
        if title:
            blocks.append(self._build_heading_block({"title": title, "header_size": "h4"}))
        if body:
            blocks.append(self._build_paragraph_block({"editor": body}))

        safe_type = re.sub(r"[^a-z0-9_-]", "", str(alert_type), flags=re.IGNORECASE)
        return self._wrap_in_group(
            "\n".join(blocks),
            {"className": f"devtb-alert is-style-{safe_type}"},
        )

    # ----- marker fallback -----

    def _convert_as_marker(self, component: Dict[str, Any]) -> str:
        widget_type = component.get("widgetType", "unknown")
        settings = component.get("settings", {}) or {}
        framework = "elementor"

        title = (
            settings.get("title")
            or settings.get("title_text")
            or settings.get("heading")
            or settings.get("alert_title")
            or ""
        )
        body = component.get("content") or settings.get("editor") or settings.get("description") or ""

        inner_parts: List[str] = []
        if title:
            inner_parts.append(f"<strong>{escape(str(title))}</strong>")
        if body:
            rendered = body if self._looks_like_html(str(body)) else f"<p>{escape(str(body))}</p>"
            inner_parts.append(rendered)

        inner = (
            f'<div class="devtb-unconverted" data-devtb-source="{escape(framework)}:{escape(str(widget_type))}">'
            f'{"".join(inner_parts)}</div>'
        )
        comment = (
            f'<!-- devtb: unconverted {escape(framework)} widget "{escape(str(widget_type))}" -->'
        )
        return self._build_block("core/html", {}, f"{comment}\n{inner}")

    # ----- containers -----

    def _build_columns_block(self, columns: List[Dict[str, Any]], settings: Dict[str, Any]) -> str:
        column_blocks = [self._convert_column(c) for c in columns if isinstance(c, dict)]
        inner = "\n".join(column_blocks)
        html = f'<div class="wp-block-columns">\n{inner}\n</div>'
        return self._build_block("core/columns", self._denormalize_settings(settings), html)

    def _build_group_block(self, children: List[Dict[str, Any]], settings: Dict[str, Any]) -> str:
        inner_blocks: List[str] = []
        for child in children:
            if not isinstance(child, dict):
                continue
            el_type = child.get("elType")
            if el_type == "widget":
                inner_blocks.append(self._convert_widget(child))
            elif el_type == "column":
                inner_blocks.append(self._convert_column(child))
            elif el_type in ("section", "container"):
                inner_blocks.append(self._convert_section(child))
            else:
                inner_blocks.append(self._convert_component(child))
        inner = "\n".join(b for b in inner_blocks if b)
        return self._wrap_in_group(inner, self._denormalize_settings(settings))

    def _wrap_in_group(self, inner_content: str, attrs: Dict[str, Any]) -> str:
        html = f'<div class="wp-block-group">\n{inner_content}\n</div>'
        return self._build_block("core/group", attrs, html)

    # ----- helpers -----

    def _build_block(self, block_type: str, attrs: Dict[str, Any], html: str) -> str:
        # Canonical WordPress serialization drops the `core/` namespace from core blocks.
        name = block_type[5:] if block_type.startswith("core/") else block_type
        if attrs:
            attrs_json = json.dumps(attrs, separators=(",", ":"), ensure_ascii=False)
            return f"<!-- wp:{name} {attrs_json} -->\n{html}\n<!-- /wp:{name} -->"
        return f"<!-- wp:{name} -->\n{html}\n<!-- /wp:{name} -->"

    def _parse_heading_level(self, value: Any) -> int:
        if isinstance(value, int):
            return max(1, min(6, value))
        if isinstance(value, str):
            match = re.match(r"^h([1-6])$", value, re.IGNORECASE)
            if match:
                return int(match.group(1))
            if value.isdigit():
                return max(1, min(6, int(value)))
        return 2

    def _looks_like_html(self, content: Any) -> bool:
        if not isinstance(content, str):
            return False
        return "<" in content and ">" in content

    def _denormalize_settings(self, settings: Dict[str, Any]) -> Dict[str, Any]:
        """Mirror PHP denormalize_attributes: project Elementor settings onto Gutenberg block attrs."""
        if not isinstance(settings, dict):
            return {}

        attrs: Dict[str, Any] = {}
        style: Dict[str, Any] = {}

        if settings.get("css_classes"):
            attrs["className"] = settings["css_classes"]
        if settings.get("_element_id") or settings.get("id"):
            attrs["anchor"] = settings.get("_element_id") or settings.get("id")

        align = settings.get("align") or settings.get("text_align")
        if align:
            attrs["textAlign"] = align

        typography: Dict[str, Any] = {}
        if settings.get("typography_font_family"):
            typography["fontFamily"] = settings["typography_font_family"]
        if settings.get("typography_font_weight"):
            typography["fontWeight"] = settings["typography_font_weight"]
        if settings.get("typography_line_height"):
            lh = settings["typography_line_height"]
            if isinstance(lh, dict) and lh.get("size"):
                typography["lineHeight"] = lh["size"]
            else:
                typography["lineHeight"] = lh
        if typography:
            style["typography"] = typography

        color: Dict[str, Any] = {}
        if settings.get("title_color") or settings.get("text_color") or settings.get("color"):
            color["text"] = (
                settings.get("title_color")
                or settings.get("text_color")
                or settings.get("color")
            )
        if settings.get("background_color"):
            color["background"] = settings["background_color"]
        if color:
            style["color"] = color

        spacing: Dict[str, Any] = {}
        padding = self._extract_spacing_quad(settings.get("padding"))
        if padding:
            spacing["padding"] = padding
        margin = self._extract_spacing_quad(settings.get("margin"))
        if margin:
            spacing["margin"] = margin
        if spacing:
            style["spacing"] = spacing

        border: Dict[str, Any] = {}
        if settings.get("border_radius"):
            br = settings["border_radius"]
            if isinstance(br, dict) and br.get("size") is not None:
                border["radius"] = f"{br['size']}{br.get('unit', 'px')}"
            else:
                border["radius"] = br
        if settings.get("border_color"):
            border["color"] = settings["border_color"]
        if border:
            style["border"] = border

        if style:
            attrs["style"] = style

        return attrs

    def _extract_spacing_quad(self, value: Any) -> Dict[str, str]:
        if not isinstance(value, dict):
            return {}
        unit = value.get("unit", "px")
        out: Dict[str, str] = {}
        for side in ("top", "right", "bottom", "left"):
            if value.get(side) not in (None, ""):
                out[side] = f"{value[side]}{unit}"
        return out
