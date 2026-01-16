"""
Translation Bridge v4 - Avada Fusion Builder Shortcode Converter.

Converts universal/parsed data TO Avada Fusion Builder shortcode format.
Generates proper shortcode structure: [fusion_builder_container][fusion_builder_row][fusion_builder_column]
"""

from typing import Any, Dict, List
from html import escape


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
        "paragraph": "fusion_text",
        "image": "fusion_imageframe",
        "button": "fusion_button",
        "divider": "fusion_separator",
        "spacer": "fusion_separator",
        "icon": "fusion_fontawesome",
        "icon-box": "fusion_content_boxes",
        "video": "fusion_youtube",
        "gallery": "fusion_gallery",
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
        "cta": "fusion_content_boxes",
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

            if el_type == "section" or el_type == "container":
                containers.append(self._convert_section(element))
            elif el_type == "widget":
                containers.append(self._wrap_in_container(self._convert_widget(element)))
            else:
                containers.append(self._wrap_in_container(self._convert_component(element)))

        return "\n\n".join(containers)

    def _convert_section(self, section: Dict[str, Any]) -> str:
        """Convert section to fusion_builder_container."""
        settings = section.get("settings", {})
        children = section.get("elements", [])

        container_attrs = self._build_container_attrs(settings)
        attrs_str = self._attrs_to_string(container_attrs)

        rows = []
        row_columns = []

        for child in children:
            if child.get("elType") == "column":
                row_columns.append(self._convert_column(child))

        if row_columns:
            rows.append(f'[fusion_builder_row]\n{chr(10).join(row_columns)}\n[/fusion_builder_row]')
        else:
            rows.append('[fusion_builder_row][fusion_builder_column type="1_1"][/fusion_builder_column][/fusion_builder_row]')

        inner = "\n".join(rows)
        return f'[fusion_builder_container{attrs_str}]\n{inner}\n[/fusion_builder_container]'

    def _convert_column(self, column: Dict[str, Any]) -> str:
        """Convert column to fusion_builder_column."""
        settings = column.get("settings", {})
        children = column.get("elements", [])

        col_size = settings.get("_column_size", 100)
        col_type = self._size_to_fusion_type(col_size)

        widgets = []
        for child in children:
            if child.get("elType") == "widget":
                widgets.append(self._convert_widget(child))
            else:
                widgets.append(self._convert_component(child))

        inner = "\n".join(widgets)
        return f'[fusion_builder_column type="{col_type}"]\n{inner}\n[/fusion_builder_column]'

    def _convert_widget(self, widget: Dict[str, Any]) -> str:
        """Convert widget to Fusion Builder element."""
        widget_type = widget.get("widgetType", "text")
        settings = widget.get("settings", {})
        return self._build_element(widget_type, settings)

    def _convert_component(self, component: Dict[str, Any]) -> str:
        """Convert generic component to Fusion Builder element."""
        comp_type = component.get("type", "text")
        attrs = component.get("attributes", component.get("settings", {}))
        content = component.get("content", "")
        return self._build_element(comp_type, attrs, content)

    def _build_element(self, comp_type: str, settings: Dict[str, Any], content: str = "") -> str:
        """Build Fusion Builder element shortcode."""
        element_name = self.ELEMENT_TYPE_MAP.get(comp_type, "fusion_text")

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
        elif element_name == "fusion_code":
            html = content or settings.get("html", "")
            return f'[fusion_code]{html}[/fusion_code]'
        elif element_name == "fusion_alert":
            return self._build_alert(settings)
        else:
            text = content or settings.get("editor", settings.get("title", ""))
            return f'[fusion_text]{text}[/fusion_text]'

    def _build_title(self, settings: Dict[str, Any]) -> str:
        """Build fusion_title shortcode."""
        title = settings.get("title", "Heading")
        size = settings.get("header_size", "2")
        if size.startswith("h"):
            size = size[1]

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

        attrs = {"image": url, "style_type": "none"}
        return f'[fusion_imageframe{self._attrs_to_string(attrs)} /]'

    def _build_button(self, settings: Dict[str, Any]) -> str:
        """Build fusion_button shortcode."""
        text = settings.get("text", "Click Here")
        link = settings.get("link", {})
        url = link.get("url", "#") if isinstance(link, dict) else "#"

        attrs = {"link": url, "target": "_self"}
        return f'[fusion_button{self._attrs_to_string(attrs)}]{escape(text)}[/fusion_button]'

    def _build_video(self, settings: Dict[str, Any]) -> str:
        """Build fusion_youtube shortcode."""
        url = settings.get("youtube_url", "")
        video_id = ""
        if "v=" in url:
            video_id = url.split("v=")[1].split("&")[0]
        elif "youtu.be" in url:
            video_id = url.split("/")[-1]

        return f'[fusion_youtube id="{video_id}" /]'

    def _build_gallery(self, settings: Dict[str, Any]) -> str:
        """Build fusion_gallery shortcode."""
        gallery = settings.get("gallery", [])
        ids = [str(img.get("id", "")) for img in gallery if isinstance(img, dict) and img.get("id")]

        attrs = {"image_ids": ",".join(ids), "layout": "grid"}
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

    def _build_alert(self, settings: Dict[str, Any]) -> str:
        """Build fusion_alert shortcode."""
        title = settings.get("alert_title", "")
        description = settings.get("alert_description", "")
        alert_type = settings.get("alert_type", "general")

        type_map = {"info": "general", "success": "success", "warning": "warning", "danger": "error"}
        fusion_type = type_map.get(alert_type, "general")

        return f'[fusion_alert type="{fusion_type}"]{title} {description}[/fusion_alert]'

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
