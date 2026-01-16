"""
Translation Bridge v4 - WPBakery (Visual Composer) Shortcode Converter.

Converts universal/parsed data TO WPBakery shortcode format.
Generates proper shortcode structure: [vc_row][vc_column][vc_element][/vc_column][/vc_row]
"""

from typing import Any, Dict, List
from html import escape


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
        "paragraph": "vc_column_text",
        "image": "vc_single_image",
        "button": "vc_btn",
        "divider": "vc_separator",
        "spacer": "vc_empty_space",
        "icon": "vc_icon",
        "video": "vc_video",
        "gallery": "vc_gallery",
        "tabs": "vc_tta_tabs",
        "accordion": "vc_tta_accordion",
        "testimonial": "vc_column_text",
        "counter": "vc_counter",
        "progress": "vc_progress_bar",
        "html": "vc_raw_html",
        "cta": "vc_cta",
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

            if el_type == "section" or el_type == "container":
                rows.append(self._convert_section(element))
            elif el_type == "widget":
                rows.append(self._wrap_in_row(self._convert_widget(element)))
            else:
                rows.append(self._wrap_in_row(self._convert_component(element)))

        return "\n\n".join(rows)

    def _convert_section(self, section: Dict[str, Any]) -> str:
        """Convert section to vc_row."""
        settings = section.get("settings", {})
        children = section.get("elements", [])

        attrs = self._build_row_attrs(settings)
        attrs_str = self._attrs_to_string(attrs)

        columns = []
        for child in children:
            if child.get("elType") == "column":
                columns.append(self._convert_column(child))

        if not columns:
            # Create single full-width column
            columns = ['[vc_column][/vc_column]']

        inner = "\n".join(columns)
        return f'[vc_row{attrs_str}]\n{inner}\n[/vc_row]'

    def _convert_column(self, column: Dict[str, Any]) -> str:
        """Convert column to vc_column."""
        settings = column.get("settings", {})
        children = column.get("elements", [])

        col_size = settings.get("_column_size", 100)
        width = self._size_to_wpbakery_width(col_size)

        attrs = {"width": width} if width != "1/1" else {}
        attrs_str = self._attrs_to_string(attrs)

        widgets = []
        for child in children:
            if child.get("elType") == "widget":
                widgets.append(self._convert_widget(child))
            else:
                widgets.append(self._convert_component(child))

        inner = "\n".join(widgets)
        return f'[vc_column{attrs_str}]\n{inner}\n[/vc_column]'

    def _convert_widget(self, widget: Dict[str, Any]) -> str:
        """Convert widget to WPBakery element."""
        widget_type = widget.get("widgetType", "text")
        settings = widget.get("settings", {})
        return self._build_element(widget_type, settings)

    def _convert_component(self, component: Dict[str, Any]) -> str:
        """Convert generic component to WPBakery element."""
        comp_type = component.get("type", "text")
        attrs = component.get("attributes", component.get("settings", {}))
        content = component.get("content", "")
        return self._build_element(comp_type, attrs, content)

    def _build_element(self, comp_type: str, settings: Dict[str, Any], content: str = "") -> str:
        """Build WPBakery element shortcode."""
        element_name = self.ELEMENT_TYPE_MAP.get(comp_type, "vc_column_text")

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
        elif element_name == "vc_video":
            return self._build_video(settings)
        elif element_name == "vc_gallery":
            return self._build_gallery(settings)
        elif element_name == "vc_tta_tabs":
            return self._build_tabs(settings)
        elif element_name == "vc_tta_accordion":
            return self._build_accordion(settings)
        elif element_name == "vc_raw_html":
            html = content or settings.get("html", "")
            return f'[vc_raw_html]{html}[/vc_raw_html]'
        elif element_name == "vc_cta":
            return self._build_cta(settings)
        else:
            text = content or settings.get("editor", settings.get("title", ""))
            return f'[vc_column_text]{text}[/vc_column_text]'

    def _build_heading(self, settings: Dict[str, Any]) -> str:
        """Build vc_custom_heading shortcode."""
        title = settings.get("title", "Heading")
        tag = settings.get("header_size", "h2")
        align = settings.get("align", "")

        attrs = {"text": title, "font_container": f"tag:{tag}"}
        if align:
            attrs["font_container"] += f"|text_align:{align}"

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

        attrs = {}
        if img_id:
            attrs["image"] = img_id
        elif url:
            attrs["source"] = "external_link"
            attrs["external_img_src"] = url

        return f'[vc_single_image{self._attrs_to_string(attrs)}]'

    def _build_button(self, settings: Dict[str, Any]) -> str:
        """Build vc_btn shortcode."""
        text = settings.get("text", "Click Here")
        link = settings.get("link", {})
        url = link.get("url", "#") if isinstance(link, dict) else "#"

        attrs = {"title": text, "link": f"url:{escape(url)}"}
        return f'[vc_btn{self._attrs_to_string(attrs)}]'

    def _build_video(self, settings: Dict[str, Any]) -> str:
        """Build vc_video shortcode."""
        url = settings.get("youtube_url", settings.get("video_url", ""))
        return f'[vc_video link="{escape(url)}"]'

    def _build_gallery(self, settings: Dict[str, Any]) -> str:
        """Build vc_gallery shortcode."""
        gallery = settings.get("gallery", [])
        ids = [str(img.get("id", "")) for img in gallery if isinstance(img, dict) and img.get("id")]
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

    def _build_cta(self, settings: Dict[str, Any]) -> str:
        """Build vc_cta shortcode."""
        title = settings.get("title", "")
        content = settings.get("description", "")
        btn_text = settings.get("button", "Click Here")
        link = settings.get("link", {})
        url = link.get("url", "#") if isinstance(link, dict) else "#"

        attrs = {"h2": title, "txt_align": "center", "add_button": "bottom",
                 "btn_title": btn_text, "btn_link": f"url:{escape(url)}"}
        return f'[vc_cta{self._attrs_to_string(attrs)}]{content}[/vc_cta]'

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
