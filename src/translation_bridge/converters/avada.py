"""
Translation Bridge v4 - Avada Fusion Builder Shortcode Converter.

Converts universal/parsed data TO Avada Fusion Builder shortcode format.
Generates proper shortcode structure: [fusion_builder_container][fusion_builder_row][fusion_builder_column]
"""

from typing import Any, Dict, List
from html import escape


# Upstream framework version this converter is calibrated against.
TARGET_CMS_VERSION: str = "7.15.3"

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


class AvadaConverter:
    """
    Converts parsed content to Avada Fusion Builder shortcode format.

    Fusion Builder structure:
    [fusion_builder_container]
      [fusion_builder_row]
        [fusion_builder_column type="1_1"]
          [fusion_element]
        [/fusion_builder_column]
      [/fusion_builder_row]
    [/fusion_builder_container]
    """

    ELEMENT_TYPE_MAP = {
        "heading": "fusion_title",
        "text": "fusion_text",
        "text-editor": "fusion_text",
        "paragraph": "fusion_text",
        "image": "fusion_imageframe",
        "button": "fusion_button",
        "divider": "fusion_separator",
        "spacer": "fusion_separator",
        "icon": "fusion_fontawesome",
        "icon-box": "fusion_content_boxes",
        "icon-list": "fusion_checklist",
        "video": "fusion_youtube",
        "gallery": "fusion_gallery",
        "image-gallery": "fusion_gallery",
        "carousel": "fusion_images",
        "tabs": "fusion_tabs",
        "accordion": "fusion_accordion",
        "testimonial": "fusion_testimonials",
        "counter": "fusion_counters_circle",
        "progress": "fusion_progress",
        "form": "fusion_form",
        "nav": "fusion_menu",
        "menu": "fusion_menu",
        "html": "fusion_code",
        "cta": "fusion_tagline_box",
        "call-to-action": "fusion_tagline_box",
        "price-table": "fusion_pricing_table",
        "alert": "fusion_alert",
    }

    def convert(self, data: Any) -> str:
        """Convert universal data to Avada shortcode string."""
        if isinstance(data, dict):
            if "elements" in data:
                return self._convert_elements(data["elements"])
            return self._wrap_in_container(self._convert_component(data))

        elif isinstance(data, list):
            return self._convert_elements(data)

        return ""

    def _convert_elements(self, elements: List[Dict[str, Any]]) -> str:
        """Convert list of elements to shortcodes."""
        containers = []

        for element in elements:
            el_type = element.get("elType", "")

            if el_type in ("section", "container", "column"):
                containers.append(self._convert_section(element))
            elif el_type == "widget":
                containers.append(self._wrap_in_container(self._convert_widget(element)))
            else:
                containers.append(self._wrap_in_container(self._convert_component(element)))

        return "\n\n".join(containers)

    def _convert_section(self, section: Dict[str, Any]) -> str:
        """Convert a structural element to fusion_builder_container."""
        settings = section.get("settings", {})

        container_attrs = self._build_container_attrs(settings)
        attrs_str = self._attrs_to_string(container_attrs)

        rows = self._convert_rows(section)
        if not rows:
            rows = ['[fusion_builder_row][fusion_builder_column type="1_1"][/fusion_builder_column][/fusion_builder_row]']

        inner = "\n".join(rows)
        return f'[fusion_builder_container{attrs_str}]\n{inner}\n[/fusion_builder_container]'

    def _convert_rows(self, element: Dict[str, Any]) -> List[str]:
        """Flatten a structural element into fusion_builder_row strings.

        Nested containers (e.g. DIVI rows inside a section) each become
        their own row inside the parent container.
        """
        rows = []
        columns = []
        loose = []

        for child in element.get("elements", []):
            child_type = child.get("elType", "")
            if child_type == "column":
                columns.append(self._convert_column(child))
            elif child_type in ("section", "container"):
                rows.extend(self._convert_rows(child))
            elif child_type == "widget":
                loose.append(self._convert_widget(child))
            else:
                loose.append(self._convert_component(child))

        if loose:
            # Widgets without a column wrapper get a default full-width column.
            columns.append(f'[fusion_builder_column type="1_1"]\n{chr(10).join(loose)}\n[/fusion_builder_column]')

        if columns:
            rows.insert(0, f'[fusion_builder_row]\n{chr(10).join(columns)}\n[/fusion_builder_row]')

        return rows

    def _convert_column(self, column: Dict[str, Any], inner: bool = False) -> str:
        """Convert column to fusion_builder_column (or the inner variant)."""
        settings = column.get("settings", {})
        children = column.get("elements", [])

        col_size = settings.get("_column_size", 100)
        col_type = self._size_to_fusion_type(col_size)
        tag = "fusion_builder_column_inner" if inner else "fusion_builder_column"

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
        return f'[{tag} type="{col_type}"]\n{content}\n[/{tag}]'

    def _convert_inner_row(self, element: Dict[str, Any]) -> str:
        """Convert a structural element nested inside a column to an inner row."""
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
            inner_cols.append(f'[fusion_builder_column_inner type="1_1"]\n{chr(10).join(loose)}\n[/fusion_builder_column_inner]')

        inner = "\n".join(inner_cols) if inner_cols else '[fusion_builder_column_inner type="1_1"][/fusion_builder_column_inner]'
        return f'[fusion_builder_row_inner]\n{inner}\n[/fusion_builder_row_inner]'

    def _convert_widget(self, widget: Dict[str, Any]) -> str:
        """Convert widget to Fusion Builder element."""
        widget_type = widget.get("widgetType", "text")
        settings = widget.get("settings", {})

        parts = [self._build_element(widget_type, settings)]
        # Composite widgets (e.g. social-icons) can carry nested child widgets.
        for child in widget.get("elements", []) or []:
            if isinstance(child, dict) and child.get("elType") == "widget":
                parts.append(self._convert_widget(child))

        return "\n".join(p for p in parts if p)

    def _convert_component(self, component: Dict[str, Any]) -> str:
        """Convert generic component to Fusion Builder element."""
        comp_type = component.get("type", "text")
        attrs = component.get("attributes", component.get("settings", {}))
        content = component.get("content", "")
        return self._build_element(comp_type, attrs, content)

    def _build_element(self, comp_type: str, settings: Dict[str, Any], content: str = "") -> str:
        """Build Fusion Builder element shortcode."""
        element_name = self.ELEMENT_TYPE_MAP.get(comp_type, "")

        if element_name == "fusion_title":
            return self._build_title(settings)
        elif element_name == "fusion_text":
            return self._build_text(settings, content)
        elif element_name == "fusion_imageframe":
            return self._build_image(settings)
        elif element_name == "fusion_button":
            return self._build_button(settings)
        elif element_name == "fusion_separator":
            return '[fusion_separator style_type="default" /]'
        elif element_name == "fusion_fontawesome":
            return self._build_icon(settings)
        elif element_name == "fusion_youtube":
            return self._build_video(settings)
        elif element_name == "fusion_gallery":
            return self._build_gallery(settings)
        elif element_name == "fusion_tabs":
            return self._build_tabs(settings)
        elif element_name == "fusion_accordion":
            return self._build_accordion(settings)
        elif element_name == "fusion_testimonials":
            return self._build_testimonial(settings)
        elif element_name == "fusion_counters_circle":
            return self._build_counter(settings)
        elif element_name == "fusion_progress":
            return self._build_progress(settings)
        elif element_name == "fusion_content_boxes":
            return self._build_icon_box(settings)
        elif element_name == "fusion_checklist":
            return self._build_icon_list(settings)
        elif element_name == "fusion_pricing_table":
            return self._build_price_table(settings)
        elif element_name == "fusion_tagline_box":
            return self._build_cta(settings)
        elif element_name == "fusion_code":
            html = content or settings.get("html", "")
            return f'[fusion_code]{html}[/fusion_code]'
        elif element_name == "fusion_alert":
            return self._build_alert(settings)
        else:
            return self._build_fallback(settings, content)

    def _build_title(self, settings: Dict[str, Any]) -> str:
        """Build fusion_title shortcode."""
        title = settings.get("title", "Heading")
        size = str(settings.get("header_size", "2"))
        if size.startswith("h"):
            size = size[1:]

        attrs = {"size": size, "content_align": settings.get("align", "left")}
        return f'[fusion_title{self._attrs_to_string(attrs)}]{escape(title)}[/fusion_title]'

    def _build_text(self, settings: Dict[str, Any], content: str = "") -> str:
        """Build fusion_text shortcode."""
        text = content or settings.get("editor", settings.get("text", ""))
        return f'[fusion_text]{text}[/fusion_text]'

    def _build_image(self, settings: Dict[str, Any]) -> str:
        """Build fusion_imageframe shortcode."""
        image = settings.get("image", {})
        url = image.get("url", "") if isinstance(image, dict) else ""
        alt = image.get("alt", "") if isinstance(image, dict) else ""

        attrs = {"image": url, "alt": alt, "style_type": "none"}
        return f'[fusion_imageframe{self._attrs_to_string(attrs)} /]'

    def _build_button(self, settings: Dict[str, Any]) -> str:
        """Build fusion_button shortcode."""
        text = settings.get("text", "Click Here")
        link = settings.get("link", {})
        url = link.get("url", "#") if isinstance(link, dict) else "#"

        attrs = {"link": url, "target": "_self"}
        return f'[fusion_button{self._attrs_to_string(attrs)}]{escape(text)}[/fusion_button]'

    def _build_icon(self, settings: Dict[str, Any]) -> str:
        """Build fusion_fontawesome shortcode."""
        icon = settings.get("selected_icon", {})
        value = icon.get("value", "") if isinstance(icon, dict) else str(icon or "")
        return f'[fusion_fontawesome icon="{escape(value)}" /]'

    def _build_video(self, settings: Dict[str, Any]) -> str:
        """Build fusion_youtube shortcode."""
        url = settings.get("youtube_url", "")
        video_id = ""
        if "v=" in url:
            video_id = url.split("v=")[1].split("&")[0]
        elif "youtu.be" in url:
            video_id = url.split("/")[-1]

        return f'[fusion_youtube id="{video_id or escape(url)}" /]'

    def _build_gallery(self, settings: Dict[str, Any]) -> str:
        """Build fusion_gallery shortcode."""
        gallery = settings.get("wp_gallery") or settings.get("gallery") or []

        ids = []
        images = []
        for img in gallery:
            if not isinstance(img, dict):
                continue
            if img.get("id"):
                ids.append(str(img["id"]))
            if img.get("url"):
                img_attrs = {"image": img.get("url", ""), "image_id": img.get("id", ""),
                             "alt": img.get("alt", "")}
                images.append(f'[fusion_gallery_image{self._attrs_to_string(img_attrs)} /]')

        attrs = {"layout": "grid"}
        if images:
            return f'[fusion_gallery{self._attrs_to_string(attrs)}]\n{chr(10).join(images)}\n[/fusion_gallery]'

        attrs["image_ids"] = ",".join(ids)
        return f'[fusion_gallery{self._attrs_to_string(attrs)} /]'

    def _build_tabs(self, settings: Dict[str, Any]) -> str:
        """Build fusion_tabs shortcode."""
        tabs = settings.get("tabs", [])
        tab_items = []
        for tab in tabs:
            title = tab.get("tab_title", "Tab")
            content = tab.get("tab_content", "")
            tab_items.append(f'[fusion_tab title="{escape(title)}"]\n{content}\n[/fusion_tab]')

        return f'[fusion_tabs]\n{chr(10).join(tab_items)}\n[/fusion_tabs]'

    def _build_accordion(self, settings: Dict[str, Any]) -> str:
        """Build fusion_accordion shortcode."""
        items = settings.get("tabs", [])
        acc_items = []
        for item in items:
            title = item.get("tab_title", "Item")
            content = item.get("tab_content", "")
            acc_items.append(f'[fusion_toggle title="{escape(title)}"]\n{content}\n[/fusion_toggle]')

        return f'[fusion_accordion]\n{chr(10).join(acc_items)}\n[/fusion_accordion]'

    def _build_testimonial(self, settings: Dict[str, Any]) -> str:
        """Build fusion_testimonials shortcode."""
        content = settings.get("testimonial_content", "")
        name = settings.get("testimonial_name", "")
        job = settings.get("testimonial_job", "")

        attrs = {"name": name, "company": job}
        return f'[fusion_testimonials]\n[fusion_testimonial{self._attrs_to_string(attrs)}]\n{content}\n[/fusion_testimonial]\n[/fusion_testimonials]'

    def _build_counter(self, settings: Dict[str, Any]) -> str:
        """Build fusion_counters_circle shortcode."""
        number = settings.get("ending_number", "100")
        title = settings.get("title", "")

        return f'[fusion_counters_circle]\n[fusion_counter_circle value="{number}" filledcolor="" unfilledcolor="" size="220" speed="1500" text="{escape(title)}" /]\n[/fusion_counters_circle]'

    def _build_progress(self, settings: Dict[str, Any]) -> str:
        """Build fusion_progress shortcode."""
        percent = settings.get("percent", {})
        value = percent.get("size", 50) if isinstance(percent, dict) else 50
        title = settings.get("title", "")

        return f'[fusion_progress]\n[fusion_progress_bar name="{escape(title)}" percentage="{value}" /]\n[/fusion_progress]'

    def _build_icon_box(self, settings: Dict[str, Any]) -> str:
        """Build fusion_content_boxes shortcode."""
        title = settings.get("title_text", settings.get("title", ""))
        description = settings.get("description_text", settings.get("description", ""))
        icon = settings.get("selected_icon", {})
        icon_value = icon.get("value", "") if isinstance(icon, dict) else ""
        image = settings.get("image", {})
        img_url = image.get("url", "") if isinstance(image, dict) else ""
        img_alt = image.get("alt", "") if isinstance(image, dict) else ""
        link = settings.get("link", {})
        url = link.get("url", "") if isinstance(link, dict) else ""
        btn_text = settings.get("button_text", "")

        attrs = {"title": title, "icon": icon_value, "image": img_url,
                 "image_alt": img_alt, "link": url, "linktext": btn_text}
        return (f'[fusion_content_boxes layout="icon-with-title" columns="1"]\n'
                f'[fusion_content_box{self._attrs_to_string(attrs)}]\n{description}\n[/fusion_content_box]\n'
                f'[/fusion_content_boxes]')

    def _build_icon_list(self, settings: Dict[str, Any]) -> str:
        """Build fusion_checklist shortcode."""
        items = settings.get("icon_list", [])
        lis = [f'[fusion_li_item]{item.get("text", "")}[/fusion_li_item]'
               for item in items if isinstance(item, dict)]
        return f'[fusion_checklist]\n{chr(10).join(lis)}\n[/fusion_checklist]'

    def _build_price_table(self, settings: Dict[str, Any]) -> str:
        """Build fusion_pricing_table shortcode."""
        heading = settings.get("heading", "")
        currency = settings.get("currency_symbol", "")
        price = settings.get("price", "")
        period = settings.get("period", "")
        features = settings.get("features", [])
        btn_text = settings.get("button_text", "")
        btn_url = settings.get("button_url", "")

        parts = []
        if price:
            parts.append(f'[fusion_pricing_price currency="{escape(str(currency))}" price="{escape(str(price))}" time="{escape(str(period))}" /]')
        for item in features:
            if isinstance(item, dict):
                parts.append(f'[fusion_pricing_row]{item.get("item_text", "")}[/fusion_pricing_row]')
        if btn_text or btn_url:
            parts.append(f'[fusion_pricing_footer][fusion_button link="{escape(btn_url)}"]{escape(btn_text)}[/fusion_button][/fusion_pricing_footer]')

        inner = "\n".join(parts)
        return (f'[fusion_pricing_table type="1"]\n'
                f'[fusion_pricing_column title="{escape(str(heading))}"]\n{inner}\n[/fusion_pricing_column]\n'
                f'[/fusion_pricing_table]')

    def _build_cta(self, settings: Dict[str, Any]) -> str:
        """Build fusion_tagline_box shortcode."""
        title = settings.get("title", "")
        description = settings.get("description", "")
        btn_text = settings.get("button_text") or settings.get("button", "")
        link = settings.get("link", {})
        url = link.get("url", "") if isinstance(link, dict) else ""

        attrs = {"title": title, "button": btn_text, "link": url}
        return f'[fusion_tagline_box{self._attrs_to_string(attrs)}]{description}[/fusion_tagline_box]'

    def _build_alert(self, settings: Dict[str, Any]) -> str:
        """Build fusion_alert shortcode."""
        title = settings.get("alert_title", "")
        description = settings.get("alert_description", "")
        alert_type = settings.get("alert_type", "general")

        type_map = {"info": "general", "success": "success", "warning": "warning", "danger": "error"}
        fusion_type = type_map.get(alert_type, "general")

        return f'[fusion_alert type="{fusion_type}"]{title} {description}[/fusion_alert]'

    def _build_fallback(self, settings: Dict[str, Any], content: str = "") -> str:
        """Last-resort mapping: keep every content-bearing setting as text."""
        parts = []
        if content:
            parts.append(content)
        parts.extend(v for v in _collect_content_values(settings) if v not in parts)
        return f'[fusion_text]{chr(10).join(parts)}[/fusion_text]'

    def _build_container_attrs(self, settings: Dict[str, Any]) -> Dict[str, str]:
        """Build container attributes from settings."""
        attrs = {"hundred_percent": "no", "equal_height_columns": "no"}

        bg_color = settings.get("background_color", "")
        if bg_color and not bg_color.startswith("globals"):
            attrs["background_color"] = bg_color

        return attrs

    def _wrap_in_container(self, content: str) -> str:
        """Wrap content in container/row/column structure."""
        return f'''[fusion_builder_container]
[fusion_builder_row]
[fusion_builder_column type="1_1"]
{content}
[/fusion_builder_column]
[/fusion_builder_row]
[/fusion_builder_container]'''

    def _size_to_fusion_type(self, size: int) -> str:
        """Convert size percentage to Fusion column type."""
        if size >= 100: return "1_1"
        if size >= 83: return "5_6"
        if size >= 75: return "3_4"
        if size >= 66: return "2_3"
        if size >= 60: return "3_5"
        if size >= 50: return "1_2"
        if size >= 40: return "2_5"
        if size >= 33: return "1_3"
        if size >= 25: return "1_4"
        if size >= 20: return "1_5"
        if size >= 16: return "1_6"
        return "1_1"

    def _attrs_to_string(self, attrs: Dict[str, str]) -> str:
        """Convert attributes dict to shortcode attribute string."""
        if not attrs:
            return ""
        return " " + " ".join(f'{k}="{escape(str(v))}"' for k, v in attrs.items() if v)

    def get_framework(self) -> str:
        return "avada"

    def get_supported_types(self) -> List[str]:
        return list(self.ELEMENT_TYPE_MAP.keys())
