"""
Translation Bridge v4 - WPBakery (Visual Composer) Shortcode Converter.

Converts universal/parsed data TO WPBakery shortcode format.
Generates proper shortcode structure: [vc_row][vc_column][vc_element][/vc_column][/vc_row]
"""

from typing import Any, Dict, List
from html import escape


# Upstream framework version this converter is calibrated against.
TARGET_CMS_VERSION: str = "8.7.3"

# Setting keys that carry user-visible content (mirrors the fidelity
# convention in cli.py). Used by the last-resort fallback so unmapped
# widgets never drop content.
_CONTENT_KEY_PARTS = (
    "text", "title", "content", "description", "heading", "editor",
    "caption", "label", "alt", "html", "name", "job", "address", "url", "date",
)


def _is_content_key(key: str) -> bool:
    key_lower = key.lower()
    return any(part in key_lower for part in _CONTENT_KEY_PARTS)


def _collect_content_values(settings: Dict[str, Any]) -> List[str]:
    """Collect trimmed content-bearing strings from a settings dict."""
    values: List[str] = []

    def from_mapping(mapping: Dict[str, Any]) -> None:
        for key, value in mapping.items():
            if isinstance(value, str) and value.strip() and _is_content_key(key):
                values.append(value.strip())

    for key, value in settings.items():
        if isinstance(value, str) and value.strip() and _is_content_key(key):
            values.append(value.strip())
        elif isinstance(value, dict):
            from_mapping(value)
        elif isinstance(value, list):
            for item in value:
                if isinstance(item, dict):
                    from_mapping(item)

    return list(dict.fromkeys(values))


class WPBakeryConverter:
    """
    Converts parsed content to WPBakery shortcode format.

    WPBakery structure:
    [vc_row]
      [vc_column width="1/2"]
        [vc_column_text]Content[/vc_column_text]
      [/vc_column]
    [/vc_row]
    """

    ELEMENT_TYPE_MAP = {
        "heading": "vc_custom_heading",
        "text": "vc_column_text",
        "text-editor": "vc_column_text",
        "paragraph": "vc_column_text",
        "image": "vc_single_image",
        "button": "vc_btn",
        "divider": "vc_separator",
        "spacer": "vc_empty_space",
        "icon": "vc_icon",
        "video": "vc_video",
        "gallery": "vc_gallery",
        "image-gallery": "vc_gallery",
        "tabs": "vc_tta_tabs",
        "accordion": "vc_tta_accordion",
        "testimonial": "vc_column_text",
        "icon-box": "vc_column_text",
        "icon-list": "vc_column_text",
        "price-table": "vc_column_text",
        "counter": "vc_counter",
        "progress": "vc_progress_bar",
        "html": "vc_raw_html",
        "cta": "vc_cta",
        "call-to-action": "vc_cta",
        "alert": "vc_message",
    }

    def convert(self, data: Any) -> str:
        """Convert universal data to WPBakery shortcode string."""
        if isinstance(data, dict):
            if "elements" in data:
                return self._convert_elements(data["elements"])
            return self._wrap_in_row(self._convert_component(data))

        elif isinstance(data, list):
            return self._convert_elements(data)

        return ""

    def _convert_elements(self, elements: List[Dict[str, Any]]) -> str:
        """Convert list of elements to shortcodes."""
        rows = []

        for element in elements:
            el_type = element.get("elType", "")

            if el_type in ("section", "container", "column"):
                rows.append(self._convert_section(element))
            elif el_type == "widget":
                rows.append(self._wrap_in_row(self._convert_widget(element)))
            else:
                rows.append(self._wrap_in_row(self._convert_component(element)))

        return "\n\n".join(rows)

    def _convert_section(self, section: Dict[str, Any]) -> str:
        """Convert a structural element to one or more vc_row blocks."""
        settings = section.get("settings", {})
        children = section.get("elements", [])

        attrs = self._build_row_attrs(settings)
        attrs_str = self._attrs_to_string(attrs)

        columns = []
        nested_rows = []
        loose = []

        for child in children:
            child_type = child.get("elType", "")
            if child_type == "column":
                columns.append(self._convert_column(child))
            elif child_type in ("section", "container"):
                # Nested containers (e.g. DIVI rows) become sibling rows.
                nested_rows.append(self._convert_section(child))
            elif child_type == "widget":
                loose.append(self._convert_widget(child))
            else:
                loose.append(self._convert_component(child))

        if loose:
            # Widgets without a column wrapper get a default full-width column.
            columns.append(f'[vc_column]\n{chr(10).join(loose)}\n[/vc_column]')

        rows = []
        if columns or not nested_rows:
            inner = "\n".join(columns) if columns else '[vc_column][/vc_column]'
            rows.append(f'[vc_row{attrs_str}]\n{inner}\n[/vc_row]')
        rows.extend(nested_rows)

        return "\n\n".join(rows)

    def _convert_column(self, column: Dict[str, Any], inner: bool = False) -> str:
        """Convert column to vc_column (or vc_column_inner)."""
        settings = column.get("settings", {})
        children = column.get("elements", [])

        col_size = settings.get("_column_size", 100)
        width = self._size_to_wpbakery_width(col_size)
        tag = "vc_column_inner" if inner else "vc_column"

        attrs = {"width": width} if width != "1/1" else {}
        attrs_str = self._attrs_to_string(attrs)

        widgets = []
        for child in children:
            child_type = child.get("elType", "")
            if child_type == "widget":
                widgets.append(self._convert_widget(child))
            elif child_type in ("section", "container", "column"):
                widgets.append(self._convert_inner_row(child))
            else:
                widgets.append(self._convert_component(child))

        content = "\n".join(widgets)
        return f'[{tag}{attrs_str}]\n{content}\n[/{tag}]'

    def _convert_inner_row(self, element: Dict[str, Any]) -> str:
        """Convert a structural element nested inside a column to vc_row_inner."""
        inner_cols = []
        loose = []

        children = [element] if element.get("elType") == "column" else element.get("elements", [])
        for child in children:
            child_type = child.get("elType", "")
            if child_type == "column":
                inner_cols.append(self._convert_column(child, inner=True))
            elif child_type in ("section", "container"):
                loose.append(self._convert_inner_row(child))
            elif child_type == "widget":
                loose.append(self._convert_widget(child))
            else:
                loose.append(self._convert_component(child))

        if loose:
            inner_cols.append(f'[vc_column_inner]\n{chr(10).join(loose)}\n[/vc_column_inner]')

        inner = "\n".join(inner_cols) if inner_cols else '[vc_column_inner][/vc_column_inner]'
        return f'[vc_row_inner]\n{inner}\n[/vc_row_inner]'

    def _convert_widget(self, widget: Dict[str, Any]) -> str:
        """Convert widget to WPBakery element."""
        widget_type = widget.get("widgetType", "text")
        settings = widget.get("settings", {})

        parts = [self._build_element(widget_type, settings)]
        # Composite widgets (e.g. social-icons) can carry nested child widgets.
        for child in widget.get("elements", []) or []:
            if isinstance(child, dict) and child.get("elType") == "widget":
                parts.append(self._convert_widget(child))

        return "\n".join(p for p in parts if p)

    def _convert_component(self, component: Dict[str, Any]) -> str:
        """Convert generic component to WPBakery element."""
        comp_type = component.get("type", "text")
        attrs = component.get("attributes", component.get("settings", {}))
        content = component.get("content", "")
        return self._build_element(comp_type, attrs, content)

    def _build_element(self, comp_type: str, settings: Dict[str, Any], content: str = "") -> str:
        """Build WPBakery element shortcode."""
        # Canonical widgets with no 1:1 WPBakery element are built as
        # composite markup so no content-bearing setting is dropped.
        if comp_type == "testimonial":
            return self._build_testimonial(settings)
        elif comp_type == "icon-box":
            return self._build_icon_box(settings)
        elif comp_type == "icon-list":
            return self._build_icon_list(settings)
        elif comp_type == "price-table":
            return self._build_price_table(settings)

        element_name = self.ELEMENT_TYPE_MAP.get(comp_type, "")

        if element_name == "vc_custom_heading":
            return self._build_heading(settings)
        elif element_name == "vc_column_text":
            return self._build_text(settings, content)
        elif element_name == "vc_single_image":
            return self._build_image(settings)
        elif element_name == "vc_btn":
            return self._build_button(settings)
        elif element_name == "vc_separator":
            return '[vc_separator]'
        elif element_name == "vc_empty_space":
            height = settings.get("space", {})
            h = height.get("size", 32) if isinstance(height, dict) else 32
            return f'[vc_empty_space height="{h}px"]'
        elif element_name == "vc_icon":
            return self._build_icon(settings)
        elif element_name == "vc_video":
            return self._build_video(settings)
        elif element_name == "vc_gallery":
            return self._build_gallery(settings)
        elif element_name == "vc_tta_tabs":
            return self._build_tabs(settings)
        elif element_name == "vc_tta_accordion":
            return self._build_accordion(settings)
        elif element_name == "vc_progress_bar":
            return self._build_progress(settings)
        elif element_name == "vc_raw_html":
            html = content or settings.get("html", "")
            return f'[vc_raw_html]{html}[/vc_raw_html]'
        elif element_name == "vc_cta":
            return self._build_cta(settings)
        elif element_name == "vc_message":
            return self._build_alert(settings)
        else:
            return self._build_fallback(settings, content)

    def _build_heading(self, settings: Dict[str, Any]) -> str:
        """Build vc_custom_heading shortcode."""
        title = settings.get("title", "Heading")
        tag = settings.get("header_size", "h2")
        align = settings.get("align", "")
        color = settings.get("title_color", "")

        attrs = {"text": title, "font_container": f"tag:{tag}"}
        if align:
            attrs["font_container"] += f"|text_align:{align}"
        if color:
            attrs["font_container"] += f"|color:{color}"

        return f'[vc_custom_heading{self._attrs_to_string(attrs)}]'

    def _build_text(self, settings: Dict[str, Any], content: str = "") -> str:
        """Build vc_column_text shortcode."""
        text = content or settings.get("editor", settings.get("text", ""))
        return f'[vc_column_text]{text}[/vc_column_text]'

    def _build_image(self, settings: Dict[str, Any]) -> str:
        """Build vc_single_image shortcode."""
        image = settings.get("image", {})
        url = image.get("url", "") if isinstance(image, dict) else ""
        img_id = image.get("id", "") if isinstance(image, dict) else ""
        alt = image.get("alt", "") if isinstance(image, dict) else ""

        attrs = {}
        if img_id:
            attrs["image"] = img_id
        elif url:
            attrs["source"] = "external_link"
            attrs["external_img_src"] = url
        if alt:
            attrs["title"] = alt

        return f'[vc_single_image{self._attrs_to_string(attrs)}]'

    def _build_button(self, settings: Dict[str, Any]) -> str:
        """Build vc_btn shortcode."""
        text = settings.get("text", "Click Here")
        link = settings.get("link", {})
        url = link.get("url", "#") if isinstance(link, dict) else "#"

        attrs = {"title": text, "link": f"url:{escape(url)}"}
        return f'[vc_btn{self._attrs_to_string(attrs)}]'

    def _build_icon(self, settings: Dict[str, Any]) -> str:
        """Build vc_icon shortcode."""
        icon = settings.get("selected_icon", {})
        value = icon.get("value", "") if isinstance(icon, dict) else str(icon or "")

        attrs = {"icon_fontawesome": value} if value else {}
        return f'[vc_icon{self._attrs_to_string(attrs)}]'

    def _build_video(self, settings: Dict[str, Any]) -> str:
        """Build vc_video shortcode."""
        url = settings.get("youtube_url", settings.get("video_url", ""))
        return f'[vc_video link="{escape(url)}"]'

    def _build_gallery(self, settings: Dict[str, Any]) -> str:
        """Build vc_gallery shortcode."""
        gallery = settings.get("wp_gallery") or settings.get("gallery") or []
        ids = [str(img.get("id", "")) for img in gallery if isinstance(img, dict) and img.get("id")]
        srcs = [str(img.get("url", "")) for img in gallery if isinstance(img, dict) and img.get("url")]

        if srcs:
            return f'[vc_gallery type="image_grid" source="external_link" custom_srcs="{escape(",".join(srcs))}"]'
        return f'[vc_gallery type="image_grid" images="{",".join(ids)}"]'

    def _build_tabs(self, settings: Dict[str, Any]) -> str:
        """Build vc_tta_tabs shortcode."""
        tabs = settings.get("tabs", [])
        tab_items = []
        for tab in tabs:
            title = tab.get("tab_title", "Tab")
            content = tab.get("tab_content", "")
            tab_items.append(f'[vc_tta_section title="{escape(title)}"]\n[vc_column_text]{content}[/vc_column_text]\n[/vc_tta_section]')

        return f'[vc_tta_tabs]\n{chr(10).join(tab_items)}\n[/vc_tta_tabs]'

    def _build_accordion(self, settings: Dict[str, Any]) -> str:
        """Build vc_tta_accordion shortcode."""
        items = settings.get("tabs", [])
        acc_items = []
        for i, item in enumerate(items):
            title = item.get("tab_title", f"Item {i+1}")
            content = item.get("tab_content", "")
            active = 'active="true"' if i == 0 else ""
            acc_items.append(f'[vc_tta_section title="{escape(title)}" {active}]\n[vc_column_text]{content}[/vc_column_text]\n[/vc_tta_section]')

        return f'[vc_tta_accordion]\n{chr(10).join(acc_items)}\n[/vc_tta_accordion]'

    def _build_progress(self, settings: Dict[str, Any]) -> str:
        """Build vc_progress_bar shortcode."""
        percent = settings.get("percent", {})
        value = percent.get("size", 50) if isinstance(percent, dict) else (percent or 50)
        title = settings.get("title", "")
        return f'[vc_progress_bar values="{value}|{escape(str(title))}"]'

    def _build_cta(self, settings: Dict[str, Any]) -> str:
        """Build vc_cta shortcode."""
        title = settings.get("title", "")
        content = settings.get("description", "")
        btn_text = settings.get("button_text") or settings.get("button", "Click Here")
        link = settings.get("link", {})
        url = link.get("url", "#") if isinstance(link, dict) else "#"

        attrs = {"h2": title, "txt_align": "center", "add_button": "bottom",
                 "btn_title": btn_text, "btn_link": f"url:{escape(url)}"}
        return f'[vc_cta{self._attrs_to_string(attrs)}]{content}[/vc_cta]'

    def _build_alert(self, settings: Dict[str, Any]) -> str:
        """Build vc_message shortcode."""
        title = settings.get("alert_title", "")
        description = settings.get("alert_description", "")
        alert_type = settings.get("alert_type", "info")

        color = alert_type if alert_type in ("info", "success", "warning", "danger") else "info"
        body = " ".join(p for p in (f"<strong>{title}</strong>" if title else "", description) if p)
        return f'[vc_message color="{color}"]{body}[/vc_message]'

    def _build_testimonial(self, settings: Dict[str, Any]) -> str:
        """Build testimonial as composite text markup (no native VC element)."""
        content = settings.get("testimonial_content", settings.get("content", ""))
        name = settings.get("testimonial_name", "")
        job = settings.get("testimonial_job", "")

        parts = []
        if content:
            parts.append(f"<blockquote>{content}</blockquote>")
        byline = " — ".join(p for p in (name, job) if p)
        if byline:
            parts.append(f"<p>{byline}</p>")
        return f'[vc_column_text]{"".join(parts)}[/vc_column_text]'

    def _build_icon_box(self, settings: Dict[str, Any]) -> str:
        """Build icon-box as composite text markup (no native VC element)."""
        title = settings.get("title_text", settings.get("title", ""))
        description = settings.get("description_text", settings.get("description", ""))
        image = settings.get("image", {})
        img_url = image.get("url", "") if isinstance(image, dict) else ""
        img_alt = image.get("alt", "") if isinstance(image, dict) else ""
        link = settings.get("link", {})
        url = link.get("url", "") if isinstance(link, dict) else ""
        btn_text = settings.get("button_text", "")

        parts = []
        if img_url:
            parts.append(f'<img src="{escape(img_url)}" alt="{escape(img_alt)}" />')
        if title:
            parts.append(f"<h4>{title}</h4>")
        if description:
            parts.append(f"<p>{description}</p>")
        if btn_text or url:
            parts.append(f'<a href="{escape(url or "#")}">{btn_text or url}</a>')
        return f'[vc_column_text]{"".join(parts)}[/vc_column_text]'

    def _build_icon_list(self, settings: Dict[str, Any]) -> str:
        """Build icon-list as composite text markup (no native VC element)."""
        items = settings.get("icon_list", [])
        lis = "".join(f"<li>{item.get('text', '')}</li>" for item in items if isinstance(item, dict))
        return f'[vc_column_text]<ul>{lis}</ul>[/vc_column_text]'

    def _build_price_table(self, settings: Dict[str, Any]) -> str:
        """Build price-table as composite text markup (no native VC element)."""
        heading = settings.get("heading", "")
        currency = settings.get("currency_symbol", "")
        price = settings.get("price", "")
        period = settings.get("period", "")
        features = settings.get("features", [])
        btn_text = settings.get("button_text", "")
        btn_url = settings.get("button_url", "")

        parts = []
        if heading:
            parts.append(f"<h4>{heading}</h4>")
        if price:
            price_line = f"{currency}{price}" + (f"/{period}" if period else "")
            parts.append(f"<p>{price_line}</p>")
        if features:
            lis = "".join(f"<li>{item.get('item_text', '')}</li>" for item in features if isinstance(item, dict))
            parts.append(f"<ul>{lis}</ul>")
        if btn_text or btn_url:
            parts.append(f'<a href="{escape(btn_url or "#")}">{btn_text or btn_url}</a>')
        return f'[vc_column_text]{"".join(parts)}[/vc_column_text]'

    def _build_fallback(self, settings: Dict[str, Any], content: str = "") -> str:
        """Last-resort mapping: keep every content-bearing setting as text."""
        parts = []
        if content:
            parts.append(content)
        parts.extend(v for v in _collect_content_values(settings) if v not in parts)
        return f'[vc_column_text]{chr(10).join(parts)}[/vc_column_text]'

    def _build_row_attrs(self, settings: Dict[str, Any]) -> Dict[str, str]:
        """Build row attributes from settings."""
        attrs = {}
        bg_color = settings.get("background_color", "")
        if bg_color and not bg_color.startswith("globals"):
            attrs["css"] = f".vc_custom_{{background-color:{bg_color}!important;}}"
        return attrs

    def _wrap_in_row(self, content: str) -> str:
        """Wrap content in row/column structure."""
        return f'[vc_row]\n[vc_column]\n{content}\n[/vc_column]\n[/vc_row]'

    def _size_to_wpbakery_width(self, size: int) -> str:
        """Convert size percentage to WPBakery width fraction."""
        if size >= 100: return "1/1"
        if size >= 75: return "3/4"
        if size >= 66: return "2/3"
        if size >= 50: return "1/2"
        if size >= 33: return "1/3"
        if size >= 25: return "1/4"
        return "1/1"

    def _attrs_to_string(self, attrs: Dict[str, str]) -> str:
        """Convert attributes dict to shortcode attribute string."""
        if not attrs:
            return ""
        return " " + " ".join(f'{k}="{escape(str(v))}"' for k, v in attrs.items() if v)

    def get_framework(self) -> str:
        return "wpbakery"

    def get_supported_types(self) -> List[str]:
        return list(self.ELEMENT_TYPE_MAP.keys())
